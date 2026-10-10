import { Link } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { FcGoogle } from "react-icons/fc";
import { LuEye, LuSettings, LuUserRound } from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import DataTable from "@/Components/Dashboard/DataTable";
import { Avatar, CopyButton, EmptyState } from "@/Components/Dashboard/Ui";

/** Owner shop setup: has a shop / step 1 saved but no shop / never started. */
const SETUP = {
    live: { label: "Shop live", className: "bg-emerald-500/10 text-emerald-700" },
    business: { label: "Business details saved", className: "bg-amber-500/10 text-amber-800" },
    not_started: { label: "Setup not started", className: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border" },
};

const iconLink =
    "inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text";

const columns = [
    {
        id: "user",
        accessorFn: (u) => `${u.name} ${u.email}`,
        header: "User",
        enableHiding: false,
        sortingFn: (a, b) => a.original.name.localeCompare(b.original.name),
        meta: { label: "User", csv: (u) => `${u.name} <${u.email}>` },
        cell: ({ row: { original: u } }) => (
            <div className="flex min-w-0 items-center gap-3">
                <Avatar name={u.name} />
                <div className="min-w-0">
                    <p className="flex items-center gap-1.5 truncate font-medium text-brand-text">
                        <span className="truncate">{u.name}</span>
                        {u.via_google && <FcGoogle className="h-3.5 w-3.5 shrink-0" title="Signs in with Google" />}
                    </p>
                    <p className="flex items-center gap-1 truncate text-xs text-brand-muted">
                        <span className="truncate">{u.email}</span>
                        <CopyButton text={u.email} label={`Copy ${u.name}'s email`} />
                    </p>
                </div>
            </div>
        ),
    },
    {
        id: "role",
        accessorKey: "role",
        header: "Role",
        meta: { label: "Role" },
        cell: ({ getValue }) => (
            <span className="rounded-full bg-brand-bg px-2 py-0.5 text-xs font-medium capitalize text-brand-text ring-1 ring-inset ring-brand-border">
                {getValue()}
            </span>
        ),
    },
    {
        id: "shop",
        accessorFn: (u) => u.shop?.name ?? u.draft?.business_name ?? "",
        header: "Shop",
        meta: {
            label: "Shop",
            csv: (u) =>
                u.shop?.name ??
                (u.setup ? `${SETUP[u.setup].label}${u.draft?.business_name ? ` (${u.draft.business_name})` : ""}` : ""),
        },
        cell: ({ row: { original: u } }) => {
            if (!u.setup) return <span className="text-xs text-brand-muted">-</span>;
            if (u.shop) {
                return (
                    <Link href={`/admin/shops/${u.shop.id}/settings`} className="font-medium text-brand-text hover:text-brand-accent">
                        {u.shop.name}
                    </Link>
                );
            }

            return (
                <div className="min-w-0">
                    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${SETUP[u.setup].className}`}>
                        {SETUP[u.setup].label}
                    </span>
                    {u.draft && (
                        <p className="mt-1 max-w-64 text-xs text-brand-muted">
                            {[u.draft.business_name, [u.draft.town, u.draft.state, u.draft.postcode].filter(Boolean).join(" ")].filter(Boolean).join(" · ")}
                            {u.draft.phone && (
                                <>
                                    {" · "}
                                    <a href={`tel:${u.draft.phone_tel}`} className="hover:text-brand-text">
                                        {u.draft.phone}
                                    </a>
                                </>
                            )}
                        </p>
                    )}
                </div>
            );
        },
    },
    {
        id: "signin",
        accessorFn: (u) => (u.via_google && !u.has_password ? "Google" : u.via_google ? "Google + password" : "Password"),
        header: "Signs in with",
        meta: { label: "Signs in with" },
        cell: ({ getValue }) => <span className="whitespace-nowrap text-brand-muted">{getValue()}</span>,
    },
    {
        id: "created",
        accessorKey: "created_at",
        header: "Signed up",
        enableGlobalFilter: false,
        sortDescFirst: true,
        meta: { label: "Signed up", csv: (u) => u.created_label },
        cell: ({ row: { original: u } }) => <span className="whitespace-nowrap text-brand-muted">{u.created_label}</span>,
    },
    {
        id: "actions",
        header: () => <span className="sr-only">Actions</span>,
        enableSorting: false,
        enableHiding: false,
        enableGlobalFilter: false,
        meta: { csv: false, stickyRight: true },
        cell: ({ row: { original: u } }) =>
            u.shop ? (
                <div className="flex items-center justify-end gap-0.5">
                    <Link href={`/admin/shops/${u.shop.id}/settings`} aria-label={`Configure ${u.shop.name}`} title="Configure shop" className={iconLink}>
                        <LuSettings className="h-4 w-4" />
                    </Link>
                    <Link
                        href={`/admin/shops/${u.shop.id}/view-as-owner`}
                        method="post"
                        as="button"
                        aria-label={`View ${u.shop.name} as its owner`}
                        title="View as owner"
                        className={iconLink}
                    >
                        <LuEye className="h-4 w-4" />
                    </Link>
                </div>
            ) : null,
    },
];

const FILTERS = [
    ["all", "All", () => true],
    ["owners", "Owners with a shop", (u) => u.setup === "live"],
    ["no_shop", "No shop yet", (u) => u.setup === "business" || u.setup === "not_started"],
    ["admins", "Admins", (u) => u.role === "admin"],
];

export default function Users({ users }) {
    const [filter, setFilter] = useState("all");
    const counts = useMemo(() => Object.fromEntries(FILTERS.map(([key, , test]) => [key, users.filter(test).length])), [users]);
    const rows = useMemo(() => users.filter(FILTERS.find(([key]) => key === filter)[2]), [users, filter]);

    return (
        <AdminLayout
            title="Users"
            description={
                filter === "no_shop"
                    ? "Owners who signed up but have no shop yet - e.g. setup was abandoned or its last step failed. Their saved details are kept: they continue where they left off when they log in."
                    : "Every login on TaDa Tap, including owners who haven't finished setting up their shop."
            }
        >
            <DataTable
                data={rows}
                columns={columns}
                initialSorting={[{ id: "created", desc: true }]}
                searchPlaceholder="Search name, email or business"
                storageKey="admin.users.table"
                exportName="tada-tap-users"
                filteredExternally={filter !== "all"}
                onClearAll={() => setFilter("all")}
                toolbar={
                    <div className="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1" role="group" aria-label="Filter users">
                        {FILTERS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setFilter(key)}
                                aria-pressed={filter === key}
                                className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                                    filter === key
                                        ? "bg-brand-deep text-white"
                                        : "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                                }`}
                            >
                                {label}
                                <span className={`tabular-nums ${filter === key ? "text-white/70" : "text-brand-muted/80"}`}>{counts[key]}</span>
                            </button>
                        ))}
                    </div>
                }
                empty={<EmptyState icon={LuUserRound} title="No users match" />}
            />
        </AdminLayout>
    );
}
