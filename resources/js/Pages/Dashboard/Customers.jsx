import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { LuSearch, LuUsers } from 'react-icons/lu';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { Avatar, EmptyState, inputClass, Pagination, Panel } from '@/Components/Dashboard/Ui';

function StampProgress({ stamps, max }) {
    const full = stamps >= max;

    return (
        <div className="flex items-center gap-2">
            <div className="h-1.5 w-20 overflow-hidden rounded-full bg-brand-border">
                <div className="h-full rounded-full bg-brand-accent" style={{ width: `${Math.min(100, (stamps / max) * 100)}%` }} />
            </div>
            <span className={`text-xs tabular-nums ${full ? 'font-semibold text-brand-accent' : 'text-brand-muted'}`}>
                {stamps}/{max}
            </span>
        </div>
    );
}

function ConsentBadge({ on }) {
    return on ? (
        <span className="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-800">Opted in</span>
    ) : (
        <span className="text-xs text-brand-muted">–</span>
    );
}

export default function Customers({ shop, search, customers, maxStamps }) {
    const [query, setQuery] = useState(search);
    const firstRun = useRef(true);

    // Debounced live search - keeps the URL (?q=) in sync so it survives a refresh.
    useEffect(() => {
        if (firstRun.current) {
            firstRun.current = false;
            return;
        }

        const timeout = setTimeout(() => {
            router.get('/dashboard/customers', query ? { q: query } : {}, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);

        return () => clearTimeout(timeout);
    }, [query]);

    return (
        <OwnerLayout shop={shop} title="Customers" description="Everyone who has a card at your shop.">
            <Panel
                bodyClassName=""
                title={`${customers.total} ${customers.total === 1 ? 'customer' : 'customers'}`}
                action={
                    <div className="relative w-full sm:w-64">
                        <LuSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search by name"
                            aria-label="Search customers by name"
                            className={`${inputClass} pl-9`}
                        />
                    </div>
                }
            >
                {customers.data.length === 0 ? (
                    <EmptyState icon={LuUsers} title={search ? 'No customers match that search' : 'No customers yet'}>
                        {search ? 'Try a different name.' : 'Customers appear here after they scan your counter QR code and sign up.'}
                    </EmptyState>
                ) : (
                    <>
                        {/* Table on wider screens */}
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-brand-border text-xs uppercase tracking-wide text-brand-muted">
                                        <th className="px-5 py-3 font-medium">Name</th>
                                        <th className="px-5 py-3 font-medium">Stamps</th>
                                        <th className="px-5 py-3 font-medium">Rewards</th>
                                        <th className="px-5 py-3 font-medium">Last visit</th>
                                        <th className="px-5 py-3 font-medium">Joined</th>
                                        <th className="px-5 py-3 font-medium">Texts</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-brand-border">
                                    {customers.data.map((c) => (
                                        <tr key={c.id} className="hover:bg-brand-bg/60">
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar name={c.name} />
                                                    <span className="font-medium text-brand-text">{c.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3">
                                                <StampProgress stamps={c.stamps} max={maxStamps} />
                                            </td>
                                            <td className="px-5 py-3 tabular-nums text-brand-text">{c.rewards_claimed}</td>
                                            <td className="px-5 py-3 text-brand-muted">{c.last_visit ?? 'Not yet'}</td>
                                            <td className="px-5 py-3 text-brand-muted">{c.joined}</td>
                                            <td className="px-5 py-3">
                                                <ConsentBadge on={c.marketing_consent} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Cards on phones */}
                        <ul className="divide-y divide-brand-border md:hidden">
                            {customers.data.map((c) => (
                                <li key={c.id} className="flex items-center gap-3 px-5 py-3">
                                    <Avatar name={c.name} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-brand-text">{c.name}</p>
                                        <p className="text-xs text-brand-muted">
                                            {c.rewards_claimed} {c.rewards_claimed === 1 ? 'reward' : 'rewards'} · last visit {c.last_visit ?? 'not yet'}
                                        </p>
                                    </div>
                                    <StampProgress stamps={c.stamps} max={maxStamps} />
                                </li>
                            ))}
                        </ul>

                        <Pagination paginator={customers} only={['customers', 'search']} />
                    </>
                )}
            </Panel>
        </OwnerLayout>
    );
}
