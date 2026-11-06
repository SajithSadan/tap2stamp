import { createContext, useCallback, useContext, useMemo, useState } from "react";
import { LuCalendar, LuClock, LuEye, LuEyeOff, LuGlobe, LuKeyRound, LuMonitor } from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import DataTable from "@/Components/Dashboard/DataTable";
import SelectFilter from "@/Components/Dashboard/SelectFilter";
import { Avatar, CopyButton, EmptyState } from "@/Components/Dashboard/Ui";

const iconButton =
    "inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text disabled:opacity-50";

/** Activation states, in the order of the chips. Label + dot, never colour alone. */
const ACTIVATION = {
    used: { label: "Activated", dot: "bg-emerald-500", tone: "bg-emerald-500/10 text-emerald-700" },
    active: { label: "Waiting", dot: "bg-amber-500", tone: "bg-amber-500/10 text-amber-800" },
    none: { label: "No key", dot: "bg-slate-400", tone: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border" },
    expired: { label: "Expired", dot: "bg-orange-500", tone: "bg-orange-500/10 text-orange-800" },
    locked: { label: "Locked", dot: "bg-red-600", tone: "bg-red-500/10 text-red-700" },
};

const SIGN_IN = [
    { value: "7", label: "Signed in, last 7 days", test: (d) => d !== null && d <= 7 },
    { value: "30", label: "Signed in, last 30 days", test: (d) => d !== null && d <= 30 },
    { value: "idle", label: "Not in 30 days", test: (d) => d !== null && d > 30 },
    { value: "never", label: "Never signed in", test: (d) => d === null },
];

const SIGNED_UP = [
    { value: "7", label: "Joined, last 7 days", days: 7 },
    { value: "30", label: "Joined, last 30 days", days: 30 },
    { value: "90", label: "Joined, last 90 days", days: 90 },
];

const daysAgo = (iso) => (iso ? (Date.now() - Date.parse(iso)) / 86_400_000 : null);

/** Lets a row's activation cell report a new state back to the page (chips, counts). */
const RowUpdate = createContext(() => {});

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
                    <LuEyeOff className="h-3.5 w-3.5" />
                </button>
            </span>
        );
    }

    return (
        <span className="flex items-center gap-0.5">
            <span className="text-xs tracking-widest text-brand-muted">••••••</span>
            <button type="button" onClick={show} disabled={loading} aria-label={`Show ${customer.username}'s password`} title="Show password" className={iconButton}>
                <LuEye className="h-3.5 w-3.5" />
            </button>
            {error && <span className="text-xs text-red-700">{error}</span>}
        </span>
    );
}

/**
 * The one-time key that activates the billing app on the customer's PC. We
 * read it out to them on the phone. Hidden until "Show" (fetched one at a
 * time, logged); "New key" replaces it, e.g. for a new PC or once expired / locked.
 */
function ActivationCell({ customer }) {
    const update = useContext(RowUpdate);
    const [key, setKey] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [confirm, confirmDialog] = useConfirm();
    const state = customer.activation;

    async function load(url) {
        setLoading(true);
        setError(null);
        try {
            const { data } = await window.axios.post(url);
            setKey(data.key);
            update(customer.id, { activation: data.state, activation_expires_label: data.expires_label });
        } catch (e) {
            setError(e.response?.data?.message ?? "Couldn't load it.");
        } finally {
            setLoading(false);
        }
    }

    async function newKey() {
        const ok = await confirm({
            title: `New activation key for ${customer.restaurant_name}?`,
            message:
                state === "used"
                    ? "The PC already activated keeps working until the new key is used. Once it's used on another PC, the old PC is locked out."
                    : state === "none"
                      ? "Read the key out to the customer to unlock the app on their PC."
                      : "The current key stops working.",
            confirmLabel: "Make new key",
            danger: state === "used" || state === "active",
        });
        if (!ok) return;
        load(`/admin/pos-customers/${customer.id}/activation-key/new`);
    }

    const badge = ACTIVATION[state] ?? ACTIVATION.none;
    const hint =
        state === "used"
            ? customer.activated_label
            : state === "active" && customer.activation_expires_label
              ? `Key works until ${customer.activation_expires_label}`
              : state === "locked"
                ? "Too many wrong keys"
                : null;

    return (
        <div className="min-w-0 text-xs">
            <span className="flex items-center gap-0.5">
                <span className={`mr-1 inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 font-medium ${badge.tone}`}>
                    <span className={`h-1.5 w-1.5 rounded-full ${badge.dot}`} />
                    {badge.label}
                </span>
                {key !== null ? (
                    <>
                        <code className="rounded bg-brand-bg px-1.5 py-0.5 tracking-wider text-brand-text">{key}</code>
                        <CopyButton text={key} label="Copy activation key" />
                        <button type="button" onClick={() => setKey(null)} aria-label="Hide activation key" title="Hide" className={iconButton}>
                            <LuEyeOff className="h-3.5 w-3.5" />
                        </button>
                    </>
                ) : (
                    state !== "none" && (
                        <button
                            type="button"
                            onClick={() => load(`/admin/pos-customers/${customer.id}/activation-key`)}
                            disabled={loading}
                            aria-label={`Show ${customer.username}'s activation key`}
                            title="Show key"
                            className={iconButton}
                        >
                            <LuEye className="h-3.5 w-3.5" />
                        </button>
                    )
                )}
                <button type="button" onClick={newKey} disabled={loading} aria-label="Make a new activation key" title="New key" className={iconButton}>
                    <LuKeyRound className="h-3.5 w-3.5" />
                </button>
            </span>
            {hint && <span className={`mt-0.5 block whitespace-nowrap ${state === "locked" ? "text-red-700" : "text-brand-muted"}`}>{hint}</span>}
            {error && <span className="block text-red-700">{error}</span>}
            {confirmDialog}
        </div>
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
            <div className="flex min-w-0 items-center gap-3">
                <Avatar name={c.restaurant_name} />
                <div className="min-w-0">
                    <p className="truncate font-medium text-brand-text">{c.restaurant_name}</p>
                    <p className="truncate text-xs text-brand-muted">{c.contact_name}</p>
                </div>
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
                {c.email && (
                    <a href={`mailto:${c.email}`} className="block truncate text-brand-muted hover:text-brand-accent">
                        {c.email}
                    </a>
                )}
            </div>
        ),
    },
    {
        id: "location",
        accessorFn: (c) => c.address,
        header: "Location",
        sortingFn: (a, b) => (a.original.country ?? "").localeCompare(b.original.country ?? "") || (a.original.place ?? "").localeCompare(b.original.place ?? ""),
        meta: { label: "Location", csv: (c) => c.address },
        cell: ({ row: { original: c } }) => (
            <div className="min-w-0 max-w-56 text-xs" title={c.address}>
                <p className="truncate text-brand-text">{c.place ?? "—"}</p>
                {c.country && <p className="truncate text-brand-muted">{c.country}</p>}
            </div>
        ),
    },
    {
        id: "login",
        accessorKey: "username",
        header: "Login",
        meta: { label: "Login", csv: (c) => c.username },
        cell: ({ row: { original: c } }) => (
            <div className="min-w-0">
                <span className="flex items-center gap-0.5 font-mono text-xs text-brand-text">
                    {c.username}
                    <CopyButton text={c.username} label="Copy username" />
                </span>
                <PasswordCell customer={c} />
            </div>
        ),
    },
    {
        id: "activation",
        accessorFn: (c) => Object.keys(ACTIVATION).indexOf(c.activation),
        header: "Activation",
        enableGlobalFilter: false,
        // The state only; keys are never exported.
        meta: { label: "Activation", csv: (c) => ACTIVATION[c.activation]?.label ?? "" },
        cell: ({ row: { original: c } }) => <ActivationCell customer={c} />,
    },
    {
        id: "last_login",
        accessorFn: (c) => c.last_login_at ?? undefined,
        header: "Last sign-in",
        enableGlobalFilter: false,
        sortDescFirst: true,
        sortUndefined: "last",
        meta: { label: "Last sign-in", csv: (c) => c.last_login_label ?? "" },
        cell: ({ row: { original: c } }) => (
            <div className="whitespace-nowrap text-xs">
                <p className={c.last_login_label ? "text-brand-text" : "text-brand-muted"}>{c.last_login_label ?? "Never"}</p>
                {c.last_login_label && !c.signed_in && <p className="text-brand-muted">Signed out</p>}
            </div>
        ),
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

/** Activation chips that double as a summary: each shows how many it matches. */
function ActivationChips({ value, onChange, counts, total }) {
    const chips = [["all", "All", total], ...Object.entries(ACTIVATION).map(([key, a]) => [key, a.label, counts[key] ?? 0])];

    return (
        <div className="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1 md:flex-wrap md:overflow-visible" role="group" aria-label="Filter by activation">
            {chips.map(([key, label, count]) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onChange(key)}
                    aria-pressed={value === key}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                        value === key ? "bg-brand-deep text-white" : "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                    }`}
                >
                    {ACTIVATION[key] && <span className={`h-1.5 w-1.5 rounded-full ${ACTIVATION[key].dot}`} />}
                    {label}
                    <span className={`tabular-nums ${value === key ? "text-white/70" : "text-brand-muted/80"}`}>{count}</span>
                </button>
            ))}
        </div>
    );
}

const NO_FILTERS = { activation: "all", country: "all", signIn: "all", joined: "all" };

export default function PosCustomers({ customers }) {
    // Activation changes made on this page (new key, key shown), laid over the rows.
    const [changes, setChanges] = useState({});
    const update = useCallback((id, patch) => setChanges((c) => ({ ...c, [id]: { ...c[id], ...patch } })), []);
    const [filters, setFilters] = useState(NO_FILTERS);
    const set = (key) => (value) => setFilters((f) => ({ ...f, [key]: value }));

    const rows = useMemo(() => customers.map((c) => ({ ...c, ...changes[c.id] })), [customers, changes]);

    const matchesCountry = (c) => filters.country === "all" || (c.country_code ?? "") === filters.country;
    const matchesSignIn = (c) => filters.signIn === "all" || SIGN_IN.find((o) => o.value === filters.signIn).test(daysAgo(c.last_login_at));
    const matchesJoined = (c) => filters.joined === "all" || daysAgo(c.created_at) <= SIGNED_UP.find((o) => o.value === filters.joined).days;

    // The dropdowns narrow first; the chips count (and filter) within them.
    const narrowed = rows.filter((c) => matchesCountry(c) && matchesSignIn(c) && matchesJoined(c));
    const shown = filters.activation === "all" ? narrowed : narrowed.filter((c) => c.activation === filters.activation);

    const activationCounts = {};
    for (const c of narrowed) activationCounts[c.activation] = (activationCounts[c.activation] ?? 0) + 1;

    const countries = [];
    for (const c of rows) {
        const value = c.country_code ?? "";
        const entry = countries.find((o) => o.value === value);
        if (entry) entry.count += 1;
        else countries.push({ value, label: c.country ?? "No country", count: 1 });
    }
    countries.sort((a, b) => b.count - a.count || a.label.localeCompare(b.label));

    const isFiltered = Object.keys(NO_FILTERS).some((k) => filters[k] !== NO_FILTERS[k]);

    return (
        <AdminLayout title="POS customers" description="Restaurants on the billing desktop app. Passwords and activation keys are shown one at a time, and every look is logged.">
            <RowUpdate.Provider value={update}>
                <DataTable
                    data={shown}
                    columns={columns}
                    initialSorting={[{ id: "created", desc: true }]}
                    searchPlaceholder="Search restaurant, contact, phone or username"
                    storageKey="admin.pos-customers.table.v2"
                    exportName="tada-tap-pos-customers"
                    resetKey={JSON.stringify(filters)}
                    filteredExternally={isFiltered}
                    onClearAll={() => setFilters(NO_FILTERS)}
                    toolbar={<ActivationChips value={filters.activation} onChange={set("activation")} counts={activationCounts} total={narrowed.length} />}
                    filters={
                        <>
                            <SelectFilter label="Country" icon={LuGlobe} allLabel="All countries" value={filters.country} onChange={set("country")} options={countries} total={rows.length} />
                            <SelectFilter
                                label="Last sign-in"
                                icon={LuClock}
                                allLabel="Any sign-in"
                                value={filters.signIn}
                                onChange={set("signIn")}
                                options={SIGN_IN.map((o) => ({ ...o, count: rows.filter((c) => o.test(daysAgo(c.last_login_at))).length }))}
                            />
                            <SelectFilter
                                label="Signed up"
                                icon={LuCalendar}
                                allLabel="Joined any time"
                                value={filters.joined}
                                onChange={set("joined")}
                                options={SIGNED_UP.map((o) => ({ ...o, count: rows.filter((c) => daysAgo(c.created_at) <= o.days).length }))}
                            />
                        </>
                    }
                    empty={<EmptyState icon={LuMonitor} title={isFiltered ? "No restaurants match these filters" : "No POS customers yet"} />}
                />
            </RowUpdate.Provider>
        </AdminLayout>
    );
}
