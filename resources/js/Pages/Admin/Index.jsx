import { Link, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { FcGoogle } from "react-icons/fc";
import {
    LuCheck,
    LuCopy,
    LuExternalLink,
    LuKeyRound,
    LuPlus,
    LuSettings,
    LuStore,
    LuX,
} from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import DataTable from "@/Components/Dashboard/DataTable";
import {
    Avatar,
    CopyButton,
    EmptyState,
    primaryButton,
} from "@/Components/Dashboard/Ui";
import { formatNumber } from "@/lib/charts";

/** One-time reveal of a new owner's generated password. Dismissible, never re-shown. */
function CredentialsNotice({ password, email }) {
    const [copied, setCopied] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    if (dismissed) return null;

    async function copy() {
        try {
            await navigator.clipboard.writeText(
                email ? `Email: ${email}\nPassword: ${password}` : password,
            );
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard blocked (e.g. non-HTTPS) - the password is still select-all below.
        }
    }

    return (
        <div className="mb-6 overflow-hidden rounded-2xl border border-brand-accent/40 bg-brand-card shadow-sm">
            <div className="flex items-start gap-3 border-b border-brand-border bg-brand-accent/10 px-5 py-4">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-accent text-brand-accent-text">
                    <LuKeyRound className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-brand-text">
                        Shop created — owner login ready
                    </p>
                    <p className="mt-0.5 text-sm text-brand-muted">
                        This password is shown{" "}
                        <strong className="text-brand-text">once</strong>. Copy
                        it now and share it with the owner securely.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setDismissed(true)}
                    aria-label="Dismiss"
                    className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg"
                >
                    <LuX className="h-4 w-4" />
                </button>
            </div>

            <div className="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                {email && (
                    <div className="min-w-0">
                        <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">
                            Email
                        </p>
                        <p className="mt-1 truncate rounded-lg bg-brand-bg px-3 py-2 font-mono text-sm text-brand-text">
                            {email}
                        </p>
                    </div>
                )}
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">
                        Temporary password
                    </p>
                    <p className="mt-1 select-all break-all rounded-lg bg-brand-bg px-3 py-2 font-mono text-sm text-brand-text">
                        {password}
                    </p>
                </div>
                <button type="button" onClick={copy} className={primaryButton}>
                    {copied ? (
                        <LuCheck className="h-4 w-4" />
                    ) : (
                        <LuCopy className="h-4 w-4" />
                    )}
                    {copied ? "Copied" : "Copy login"}
                </button>
            </div>
        </div>
    );
}

/* ---------- Status ---------- */

// Status reads by label + icon-dot, never colour alone.
const STATUS = {
    active: {
        label: "Active",
        dot: "bg-emerald-500",
        tone: "bg-emerald-500/10 text-emerald-700",
        order: 0,
    },
    quiet: {
        label: "Quiet",
        dot: "bg-amber-500",
        tone: "bg-amber-500/10 text-amber-700",
        order: 1,
    },
    not_started: {
        label: "Not started",
        dot: "bg-slate-400",
        tone: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border",
        order: 2,
    },
};

function StatusBadge({ status }) {
    const s = STATUS[status];

    return (
        <span
            className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${s.tone}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${s.dot}`} />
            {s.label}
        </span>
    );
}

/** Filter chips that double as a summary: each shows how many shops it matches. */
function StatusChips({ value, onChange, counts }) {
    const chips = [
        ["all", "All"],
        ["active", "Active"],
        ["quiet", "Quiet"],
        ["not_started", "Not started"],
        ["no_owner", "No owner"],
    ];

    return (
        <div
            className="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1"
            role="group"
            aria-label="Filter by status"
        >
            {chips.map(([key, label]) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onChange(key)}
                    aria-pressed={value === key}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                        value === key
                            ? "bg-brand-deep text-white"
                            : "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                    }`}
                >
                    {STATUS[key] && (
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${STATUS[key].dot}`}
                        />
                    )}
                    {label}
                    <span
                        className={`tabular-nums ${value === key ? "text-white/70" : "text-brand-muted/80"}`}
                    >
                        {counts[key]}
                    </span>
                </button>
            ))}
        </div>
    );
}

/* ---------- Columns ---------- */

const num = (value) => (
    <span className="tabular-nums text-brand-text">{formatNumber(value)}</span>
);

// `undefined` (not null) sorts last with TanStack's sortUndefined.
const orUndefined = (value) => (value === null ? undefined : value);

function buildColumns(origin) {
    return [
        {
            id: "shop",
            accessorFn: (s) => `${s.name} ${s.slug}`,
            header: "Shop",
            sortingFn: (a, b) => a.original.name.localeCompare(b.original.name),
            meta: { label: "Shop", csv: (s) => s.name },
            enableHiding: false,
            cell: ({ row: { original: s } }) => (
                <div className="flex min-w-48 max-w-64 items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                        <LuStore className="h-4 w-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-semibold text-brand-text">
                            {s.name}
                        </p>
                        <p className="truncate font-mono text-xs text-brand-muted">
                            /s/{s.slug}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            id: "status",
            accessorKey: "status",
            header: "Status",
            sortingFn: (a, b) =>
                STATUS[a.original.status].order -
                STATUS[b.original.status].order,
            enableGlobalFilter: false,
            meta: { label: "Status", csv: (s) => STATUS[s.status].label },
            cell: ({ getValue }) => <StatusBadge status={getValue()} />,
        },
        {
            id: "owner",
            accessorFn: (s) =>
                s.owner_name ? `${s.owner_name} ${s.owner_email}` : undefined,
            header: "Owner",
            sortingFn: (a, b) =>
                (a.original.owner_name ?? "").localeCompare(
                    b.original.owner_name ?? "",
                ),
            sortUndefined: "last",
            meta: {
                label: "Owner",
                csv: (s) =>
                    s.owner_name ? `${s.owner_name} <${s.owner_email}>` : "",
            },
            cell: ({ row: { original: s } }) =>
                s.owner_name ? (
                    <div className="flex min-w-44 max-w-60 items-center gap-2.5">
                        <Avatar name={s.owner_name} />
                        <div className="min-w-0">
                            <p className="flex items-center gap-1.5 truncate text-brand-text">
                                {s.owner_name}
                                {s.owner_via_google && (
                                    <FcGoogle
                                        className="h-3.5 w-3.5 shrink-0"
                                        title="Uses Google sign-in"
                                    />
                                )}
                            </p>
                            <p className="truncate text-xs text-brand-muted">
                                {s.owner_email}
                            </p>
                        </div>
                    </div>
                ) : (
                    <span className="inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700">
                        No owner
                    </span>
                ),
        },
        {
            // Business contact from shop setup - may differ from the login owner.
            id: "contact",
            accessorFn: (s) =>
                s.contact_phone
                    ? `${s.contact_name ?? ""} ${s.contact_phone} ${s.contact_email ?? ""}`
                    : undefined,
            header: "Contact",
            enableSorting: false,
            meta: {
                label: "Contact",
                csv: (s) =>
                    [s.contact_name, s.contact_phone, s.contact_email]
                        .filter(Boolean)
                        .join(" · "),
            },
            cell: ({ row: { original: s } }) =>
                s.contact_phone ? (
                    <div className="min-w-40 max-w-56">
                        <p className="truncate text-brand-text">
                            {s.contact_name}
                        </p>
                        <a
                            href={`tel:${s.contact_phone}`}
                            className="block truncate text-xs tabular-nums text-brand-muted hover:text-brand-accent"
                        >
                            {s.contact_phone}
                        </a>
                        {s.contact_email && (
                            <a
                                href={`mailto:${s.contact_email}`}
                                className="block truncate text-xs text-brand-muted hover:text-brand-accent"
                            >
                                {s.contact_email}
                            </a>
                        )}
                    </div>
                ) : (
                    <span className="text-xs text-brand-muted">Not given</span>
                ),
        },
        {
            id: "location",
            accessorFn: (s) => orUndefined(s.address),
            header: "Location",
            sortingFn: (a, b) =>
                (a.original.town ?? "").localeCompare(b.original.town ?? ""),
            sortUndefined: "last",
            meta: {
                label: "Location",
                csv: (s) =>
                    [
                        s.address,
                        s.delivery_address
                            ? `Deliver to: ${s.delivery_address}`
                            : null,
                    ]
                        .filter(Boolean)
                        .join(" | "),
            },
            cell: ({ row: { original: s } }) =>
                s.town ? (
                    <div
                        className="min-w-32 max-w-56"
                        title={[
                            s.address,
                            s.delivery_address &&
                                `Deliver to: ${s.delivery_address}`,
                        ]
                            .filter(Boolean)
                            .join("\n")}
                    >
                        <p className="truncate text-brand-text">{s.town}</p>
                        <p className="truncate text-xs text-brand-muted">
                            {s.postcode}
                            {s.delivery_address &&
                                " · separate delivery address"}
                        </p>
                    </div>
                ) : (
                    <span className="text-xs text-brand-muted">Not given</span>
                ),
        },
        {
            id: "customers",
            accessorKey: "customers",
            header: "Customers",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Customers", align: "right" },
            cell: ({ row: { original: s } }) => (
                <div>
                    {num(s.customers)}
                    {s.new_customers_30d > 0 && (
                        <p className="text-xs text-emerald-700">
                            +{formatNumber(s.new_customers_30d)} in 30d
                        </p>
                    )}
                </div>
            ),
        },
        {
            id: "stamps_30d",
            accessorKey: "stamps_30d",
            header: "Stamps (30d)",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Stamps (30 days)", align: "right" },
            cell: ({ getValue }) => num(getValue()),
        },
        {
            id: "rewards_total",
            accessorKey: "rewards_total",
            header: "Rewards",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Rewards redeemed", align: "right" },
            cell: ({ getValue }) => num(getValue()),
        },
        {
            id: "rating",
            accessorFn: (s) => orUndefined(s.rating),
            header: "Rating",
            enableGlobalFilter: false,
            sortDescFirst: true,
            sortUndefined: "last",
            meta: {
                label: "Rating",
                align: "right",
                csv: (s) => s.rating ?? "",
            },
            cell: ({ row: { original: s } }) =>
                s.rating === null ? (
                    <span className="text-brand-muted">–</span>
                ) : (
                    <span className="whitespace-nowrap tabular-nums text-brand-text">
                        {s.rating} ★
                        <span className="ml-1 text-xs text-brand-muted">
                            ({formatNumber(s.reviews)})
                        </span>
                    </span>
                ),
        },
        {
            id: "staff",
            accessorKey: "staff",
            header: "Staff",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Staff", align: "right" },
            cell: ({ getValue }) => num(getValue()),
        },
        {
            id: "card",
            accessorFn: (s) => s.reward_title,
            header: "Loyalty card",
            enableSorting: false,
            meta: {
                label: "Loyalty card",
                csv: (s) => `${s.max_stamps} stamps: ${s.reward_title}`,
            },
            cell: ({ row: { original: s } }) => (
                <div className="max-w-56">
                    <p
                        className="truncate text-brand-text"
                        title={s.reward_title}
                    >
                        {s.reward_title}
                    </p>
                    <p className="text-xs text-brand-muted">
                        {s.max_stamps} stamps
                    </p>
                </div>
            ),
        },
        {
            id: "last_activity",
            accessorFn: (s) => orUndefined(s.last_activity_at),
            header: "Last scan",
            enableGlobalFilter: false,
            sortDescFirst: true,
            sortUndefined: "last",
            meta: { label: "Last scan", csv: (s) => s.last_activity_at ?? "" },
            cell: ({ row: { original: s } }) => (
                <span className="whitespace-nowrap text-brand-muted">
                    {s.last_activity ?? "Never"}
                </span>
            ),
        },
        {
            id: "created",
            accessorKey: "created_at",
            header: "Added",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Added", csv: (s) => s.created_label },
            cell: ({ row: { original: s } }) => (
                <span className="whitespace-nowrap text-brand-muted">
                    {s.created_label}
                </span>
            ),
        },
        {
            id: "actions",
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            enableHiding: false,
            enableGlobalFilter: false,
            meta: { csv: false, stickyRight: true },
            cell: ({ row: { original: s } }) => (
                <div className="flex items-center justify-end gap-0.5">
                    <Link
                        href={`/admin/shops/${s.id}/settings`}
                        aria-label={`Configure ${s.name}`}
                        title="Configure shop"
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                    >
                        <LuSettings className="h-4 w-4" />
                    </Link>
                    <CopyButton
                        text={`${origin}/s/${s.slug}`}
                        label={`Copy ${s.name}'s card link`}
                    />
                    <a
                        href={`/s/${s.slug}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`Open ${s.name}'s customer page`}
                        title="Open customer page"
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                    >
                        <LuExternalLink className="h-4 w-4" />
                    </a>
                </div>
            ),
        },
    ];
}

/* ---------- Page ---------- */

export default function Index({ shops, quietDays }) {
    const { flash } = usePage().props;
    const [status, setStatus] = useState("all");

    const columns = useMemo(() => buildColumns(window.location.origin), []);

    const counts = useMemo(
        () => ({
            all: shops.length,
            active: shops.filter((s) => s.status === "active").length,
            quiet: shops.filter((s) => s.status === "quiet").length,
            not_started: shops.filter((s) => s.status === "not_started").length,
            no_owner: shops.filter((s) => !s.owner_email).length,
        }),
        [shops],
    );

    const rows = useMemo(() => {
        if (status === "all") return shops;
        if (status === "no_owner") return shops.filter((s) => !s.owner_email);

        return shops.filter((s) => s.status === status);
    }, [shops, status]);

    return (
        <AdminLayout
            title="Shops"
            description={`Every shop on TaDa Tap and the owner who runs it. Quiet = no stamps for ${quietDays} days.`}
            actions={
                <Link href="/admin/shops/create" className={primaryButton}>
                    <LuPlus className="h-4 w-4" /> Add shop
                </Link>
            }
        >
            {flash?.generatedPassword && (
                <CredentialsNotice
                    password={flash.generatedPassword}
                    email={flash.createdOwnerEmail}
                />
            )}

            {shops.length === 0 ? (
                <div className="rounded-2xl border border-brand-border bg-brand-card shadow-sm">
                    <EmptyState icon={LuStore} title="No shops yet">
                        Add the first shop to create its owner login, or share
                        the sign-up page.
                        <span className="mt-4 block">
                            <Link
                                href="/admin/shops/create"
                                className={primaryButton}
                            >
                                <LuPlus className="h-4 w-4" /> Add shop
                            </Link>
                        </span>
                    </EmptyState>
                </div>
            ) : (
                <DataTable
                    data={rows}
                    columns={columns}
                    initialSorting={[{ id: "created", desc: true }]}
                    initialHidden={{ card: false, staff: false }}
                    searchPlaceholder="Search shop, link or owner"
                    storageKey="admin.shops.table"
                    exportName="tada-tap-shops"
                    toolbar={
                        <StatusChips
                            value={status}
                            onChange={setStatus}
                            counts={counts}
                        />
                    }
                    filteredExternally={status !== "all"}
                    onClearAll={() => setStatus("all")}
                    empty={<EmptyState icon={LuStore} title="No shops match" />}
                />
            )}
        </AdminLayout>
    );
}
