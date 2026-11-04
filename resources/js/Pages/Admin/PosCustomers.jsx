import { useState } from "react";
import { LuEye, LuEyeOff, LuMonitor } from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import DataTable from "@/Components/Dashboard/DataTable";
import { CopyButton, EmptyState } from "@/Components/Dashboard/Ui";

const iconButton =
    "inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text disabled:opacity-50";

/**
 * The password, hidden until "Show": fetched one at a time from the server
 * (never in the page data) and logged there. Hide forgets it again.
 */
function PasswordCell({ customer }) {
    const [password, setPassword] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    async function show() {
        setLoading(true);
        setError(null);
        try {
            const { data } = await window.axios.post(`/admin/pos-customers/${customer.id}/password`);
            setPassword(data.password);
        } catch (e) {
            setError(e.response?.data?.message ?? "Couldn't load it.");
        } finally {
            setLoading(false);
        }
    }

    if (password !== null) {
        return (
            <span className="flex items-center gap-0.5">
                <code className="rounded bg-brand-bg px-1.5 py-0.5 text-xs text-brand-text">{password}</code>
                <CopyButton text={password} label="Copy password" />
                <button type="button" onClick={() => setPassword(null)} aria-label="Hide password" title="Hide" className={iconButton}>
                    <LuEyeOff className="h-4 w-4" />
                </button>
            </span>
        );
    }

    return (
        <span className="flex items-center gap-1">
            <span className="tracking-widest text-brand-muted">••••••</span>
            <button type="button" onClick={show} disabled={loading} aria-label={`Show ${customer.username}'s password`} title="Show" className={iconButton}>
                <LuEye className="h-4 w-4" />
            </button>
            {error && <span className="text-xs text-red-700">{error}</span>}
        </span>
    );
}

const columns = [
    {
        id: "restaurant",
        accessorFn: (c) => `${c.restaurant_name} ${c.contact_name}`,
        header: "Restaurant",
        enableHiding: false,
        sortingFn: (a, b) => a.original.restaurant_name.localeCompare(b.original.restaurant_name),
        meta: { label: "Restaurant", csv: (c) => `${c.restaurant_name} (${c.contact_name})` },
        cell: ({ row: { original: c } }) => (
            <div className="min-w-0">
                <p className="truncate font-medium text-brand-text">{c.restaurant_name}</p>
                <p className="truncate text-xs text-brand-muted">{c.contact_name}</p>
            </div>
        ),
    },
    {
        id: "contact",
        accessorFn: (c) => [c.phone, c.email].filter(Boolean).join(" "),
        header: "Contact",
        meta: { label: "Contact" },
        cell: ({ row: { original: c } }) => (
            <div className="min-w-0 text-xs">
                <a href={`tel:${c.phone.replace(/[^+\d]/g, "")}`} className="block whitespace-nowrap text-brand-text hover:text-brand-accent">
                    {c.phone}
                </a>
                {c.email && <span className="block truncate text-brand-muted">{c.email}</span>}
            </div>
        ),
    },
    {
        id: "address",
        accessorKey: "address",
        header: "Address",
        meta: { label: "Address" },
        cell: ({ getValue }) => <span className="block max-w-72 text-xs text-brand-muted">{getValue()}</span>,
    },
    {
        id: "username",
        accessorKey: "username",
        header: "Username",
        meta: { label: "Username" },
        cell: ({ getValue }) => (
            <span className="flex items-center gap-0.5 font-mono text-xs text-brand-text">
                {getValue()}
                <CopyButton text={getValue()} label="Copy username" />
            </span>
        ),
    },
    {
        id: "password",
        header: "Password",
        enableSorting: false,
        enableGlobalFilter: false,
        // Never exported: passwords are only shown one at a time.
        meta: { label: "Password", csv: false },
        cell: ({ row: { original: c } }) => <PasswordCell customer={c} />,
    },
    {
        id: "last_login",
        accessorFn: (c) => c.last_login_at ?? undefined,
        header: "Last sign-in",
        enableGlobalFilter: false,
        sortDescFirst: true,
        sortUndefined: "last",
        meta: { label: "Last sign-in", csv: (c) => c.last_login_label ?? "" },
        cell: ({ row: { original: c } }) => <span className="whitespace-nowrap text-xs text-brand-muted">{c.last_login_label ?? "Never"}</span>,
    },
    {
        id: "created",
        accessorKey: "created_at",
        header: "Signed up",
        enableGlobalFilter: false,
        sortDescFirst: true,
        meta: { label: "Signed up", csv: (c) => c.created_label },
        cell: ({ row: { original: c } }) => <span className="whitespace-nowrap text-xs text-brand-muted">{c.created_label}</span>,
    },
];

export default function PosCustomers({ customers }) {
    return (
        <AdminLayout title="POS customers" description="Restaurants using the billing desktop app. Passwords are shown one at a time, and every look is in the activity log.">
            <DataTable
                data={customers}
                columns={columns}
                initialSorting={[{ id: "created", desc: true }]}
                searchPlaceholder="Search restaurant, contact, phone or username"
                storageKey="admin.pos-customers.table"
                exportName="tada-tap-pos-customers"
                empty={<EmptyState icon={LuMonitor} title="No POS customers yet" />}
            />
        </AdminLayout>
    );
}
