import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { LuArrowRight, LuShield, LuStore, LuUserCog, LuX } from 'react-icons/lu';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import DataTable from '@/Components/Dashboard/DataTable';
import { inputClass } from '@/Components/Dashboard/Ui';

/** One entry per ActivityLogger::ACTOR_TYPES. */
const ROLES = {
    admin: { label: 'Admin', plural: 'Admins', icon: LuShield, tone: 'bg-brand-deep text-white' },
    owner: { label: 'Owner', plural: 'Owners', icon: LuStore, tone: 'bg-brand-accent/15 text-brand-text' },
    staff: { label: 'Staff', plural: 'Staff', icon: LuUserCog, tone: 'bg-sky-500/10 text-sky-700' },
};

const RANGE_LABELS = { 1: 'Today', 7: '7 days', 30: '30 days', 90: '90 days' };

const when = (iso) =>
    new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

/** A before/after value, readable whatever its type. */
function Value({ value }) {
    if (value === null || value === undefined || value === '') return <span className="text-brand-muted">-</span>;
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value) && value.every((v) => typeof v !== 'object')) return value.join(', ') || <span className="text-brand-muted">none</span>;
    if (typeof value === 'object') return <code className="whitespace-pre-wrap break-all text-xs">{JSON.stringify(value, null, 1)}</code>;
    return <span className="break-words">{String(value)}</span>;
}

function ChangesDialog({ row, onClose }) {
    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 sm:items-center sm:p-4" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="What changed"
                onClick={(e) => e.stopPropagation()}
                className="flex max-h-[85vh] w-full flex-col overflow-hidden rounded-t-2xl border border-brand-border bg-brand-card sm:max-w-2xl sm:rounded-2xl"
            >
                <div className="flex items-start justify-between gap-3 border-b border-brand-border px-5 py-4">
                    <div className="min-w-0">
                        <p className="font-heading font-semibold text-brand-text">{row.description}</p>
                        <p className="mt-0.5 text-xs text-brand-muted">
                            {row.actor_name}
                            {row.as_owner && ' (as owner)'} · {row.shop?.name ?? 'Platform'} · {when(row.at)}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg" aria-label="Close">
                        <LuX className="h-4 w-4" />
                    </button>
                </div>
                <div className="overflow-y-auto">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-brand-bg text-left text-xs uppercase tracking-wide text-brand-muted">
                            <tr>
                                <th className="px-5 py-2 font-medium">Field</th>
                                <th className="px-3 py-2 font-medium">Before</th>
                                <th className="w-6" />
                                <th className="px-3 py-2 font-medium">After</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-brand-border">
                            {Object.entries(row.changes).map(([field, [before, after]]) => (
                                <tr key={field} className="align-top">
                                    <td className="px-5 py-2.5 font-medium capitalize text-brand-text">{field.replace(/_/g, ' ')}</td>
                                    <td className="px-3 py-2.5 text-red-700">
                                        <Value value={before} />
                                    </td>
                                    <td className="py-2.5 text-brand-muted">
                                        <LuArrowRight className="h-3.5 w-3.5" />
                                    </td>
                                    <td className="px-3 py-2.5 text-emerald-700">
                                        <Value value={after} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

export default function Activity({ rows, total, maxRows, filters, ranges, shops }) {
    const [open, setOpen] = useState(null);

    function filter(changes) {
        const next = { ...filters, ...changes };
        router.get(
            '/admin/activity',
            { days: next.days === 7 ? undefined : next.days, shop: next.shop || undefined, role: next.role || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const columns = useMemo(
        () => [
            {
                id: 'at',
                accessorKey: 'at',
                header: 'When',
                meta: { label: 'When', csv: (r) => r.at },
                enableHiding: false,
                cell: ({ getValue }) => <span className="whitespace-nowrap tabular-nums text-brand-muted">{when(getValue())}</span>,
            },
            {
                id: 'who',
                accessorFn: (r) => `${r.actor_name} ${r.actor_type}`,
                header: 'Who',
                meta: { label: 'Who', csv: (r) => `${r.actor_name} (${r.actor_type}${r.as_owner ? ', as owner' : ''})` },
                cell: ({ row: { original: r } }) => {
                    const role = ROLES[r.actor_type];
                    const Icon = role.icon;
                    return (
                        <div className="flex min-w-44 items-center gap-2">
                            <span className={`inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ${role.tone}`}>
                                <Icon className="h-3 w-3" /> {role.label}
                            </span>
                            <span className="truncate text-brand-text">{r.actor_name}</span>
                            {r.as_owner && <span className="shrink-0 text-[11px] text-amber-700">as owner</span>}
                        </div>
                    );
                },
            },
            {
                id: 'shop',
                accessorFn: (r) => r.shop?.name ?? '',
                header: 'Shop',
                meta: { label: 'Shop' },
                cell: ({ row: { original: r } }) =>
                    r.shop ? (
                        <a href={`/admin/shops/${r.shop.id}/settings`} className="whitespace-nowrap text-brand-text hover:underline">
                            {r.shop.name}
                        </a>
                    ) : (
                        <span className="text-brand-muted">Platform</span>
                    ),
            },
            {
                id: 'description',
                accessorKey: 'description',
                header: 'What happened',
                meta: { label: 'What happened' },
                enableHiding: false,
                cell: ({ getValue }) => <span className="block min-w-64 max-w-xl text-brand-text">{getValue()}</span>,
            },
            {
                id: 'action',
                accessorKey: 'action',
                header: 'Action',
                meta: { label: 'Action' },
                cell: ({ getValue }) => <code className="whitespace-nowrap text-xs text-brand-muted">{getValue()}</code>,
            },
            {
                id: 'changes',
                accessorFn: (r) => (r.changes ? Object.keys(r.changes).join(' ') : ''),
                header: 'Changes',
                enableSorting: false,
                meta: {
                    label: 'Changes',
                    stickyRight: true,
                    csv: (r) => (r.changes ? Object.entries(r.changes).map(([f, [a, b]]) => `${f}: ${JSON.stringify(a)} -> ${JSON.stringify(b)}`).join('; ') : ''),
                },
                cell: ({ row: { original: r } }) =>
                    r.changes ? (
                        <button type="button" onClick={() => setOpen(r)} className="whitespace-nowrap text-xs font-semibold text-brand-accent hover:underline">
                            {Object.keys(r.changes).length} {Object.keys(r.changes).length === 1 ? 'change' : 'changes'}
                        </button>
                    ) : (
                        <span className="text-brand-muted">-</span>
                    ),
            },
        ],
        [],
    );

    const chip = (active) =>
        `rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
            active ? 'bg-brand-deep text-white' : 'text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text'
        }`;

    return (
        <AdminLayout title="Activity" description="Everything admins, owners and staff do - stamps, changes with before and after, sign-ins.">
            {open && <ChangesDialog row={open} onClose={() => setOpen(null)} />}

            <DataTable
                data={rows}
                columns={columns}
                initialSorting={[{ id: 'at', desc: true }]}
                initialHidden={{ action: false }}
                searchPlaceholder="Search who, shop or what happened"
                storageKey="admin.activity.table"
                exportName="tada-tap-activity"
                pageSizes={[25, 50, 100, 250]}
                filteredExternally={filters.days !== 7 || Boolean(filters.shop) || Boolean(filters.role)}
                onClearAll={() => filter({ days: 7, shop: null, role: null })}
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex flex-wrap gap-1">
                            {ranges.map((days) => (
                                <button key={days} type="button" onClick={() => filter({ days })} className={chip(filters.days === days)}>
                                    {RANGE_LABELS[days] ?? `${days} days`}
                                </button>
                            ))}
                        </div>
                        <select value={filters.role ?? ''} onChange={(e) => filter({ role: e.target.value || null })} aria-label="Role" className={`${inputClass} !w-auto !py-1.5`}>
                            <option value="">Everyone</option>
                            {Object.entries(ROLES).map(([key, role]) => (
                                <option key={key} value={key}>
                                    {role.plural}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.shop ?? ''}
                            onChange={(e) => filter({ shop: e.target.value ? Number(e.target.value) : null })}
                            aria-label="Shop"
                            className={`${inputClass} !w-auto !py-1.5`}
                        >
                            <option value="">All shops</option>
                            {shops.map((shop) => (
                                <option key={shop.id} value={shop.id}>
                                    {shop.name}
                                </option>
                            ))}
                        </select>
                    </div>
                }
                empty={<p className="py-10 text-center text-sm text-brand-muted">Nothing recorded in this period.</p>}
            />

            {total > maxRows && (
                <p className="mt-3 text-xs text-brand-muted">
                    Showing the newest {maxRows.toLocaleString('en-GB')} of {total.toLocaleString('en-GB')} - pick a shorter period, a shop or a role to see the rest.
                </p>
            )}
        </AdminLayout>
    );
}
