import { Link, router } from '@inertiajs/react';
import { createContext, useContext, useMemo, useState } from 'react';
import { FcGoogle } from 'react-icons/fc';
import {
    LuActivity,
    LuArrowDown,
    LuArrowRight,
    LuArrowUp,
    LuExternalLink,
    LuGift,
    LuMoon,
    LuPackage,
    LuPackageCheck,
    LuTruck,
    LuQrCode,
    LuStamp,
    LuStar,
    LuStore,
    LuTrophy,
    LuUserPlus,
    LuUsers,
} from 'react-icons/lu';
import {
    BarList,
    ChangePill,
    ColumnChart,
    Heatmap,
    KpiTile,
    PeakSummary,
    RangeTabs,
    Segmented,
    slotLabel,
    StackedLines,
    WEEKDAYS,
} from '@/Components/Dashboard/Charts';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { Avatar, EmptyState, Panel } from '@/Components/Dashboard/Ui';
import { BLUE_RAMP, formatNumber, MINT_RAMP, percentChange, SERIES } from '@/lib/charts';
import { formatPence } from '@/lib/money';

const panelLink = 'inline-flex items-center gap-1 text-sm font-semibold text-brand-accent hover:underline';

/* ---------- Per-card filters ---------- */

// Every chart has its own range. Each is a separate server prop, and the
// prop name doubles as its URL param (?visits=7), so a change reloads only
// that card - and a page refresh keeps every card's choice.
const DashboardContext = createContext(null);

/** A Panel with its own 7D / 30D / 90D switch; dims only itself while it reloads. */
function FilteredPanel({ prop, range, action, className = '', children, ...panelProps }) {
    const { ranges, loading, reload } = useContext(DashboardContext);

    return (
        <Panel
            {...panelProps}
            className={`transition-opacity ${loading === prop ? 'opacity-60' : ''} ${className}`}
            action={
                <div className="flex flex-wrap items-center gap-2">
                    {action}
                    <RangeTabs ranges={ranges} value={range} busy={loading === prop} onChange={(r) => reload(prop, r)} />
                </div>
            }
        >
            {children}
        </Panel>
    );
}

function SectionTitle({ children, hint, action }) {
    return (
        <div className="mb-3 mt-8 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
            <div className="flex flex-wrap items-baseline gap-x-3">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-brand-muted">{children}</h2>
                {hint && <p className="text-xs text-brand-muted">{hint}</p>}
            </div>
            {action}
        </div>
    );
}

const lastDays = (range) => `last ${range} days`;

/* ---------- When customers visit (stamps / rewards / all) ---------- */

const VISIT_METRICS = [
    { value: 'stamps', label: 'Stamps', color: SERIES.primary },
    { value: 'rewards', label: 'Rewards redeemed', color: SERIES.secondary },
    { value: 'rate', label: 'Redemption rate' },
];

// Below this many visits in a slot, a rate is noise (1 visit = 0% or 100%).
const MIN_VISITS_FOR_RATE = 5;

const pct = (v) => `${Math.round(v * 100)}%`;

/** Rate-view facts: overall rate, and which days customers cash in most / least. */
function RateSummary({ visits }) {
    const dayRewards = visits.rewards.map((row) => row.reduce((a, b) => a + b, 0));
    const dayVisits = visits.stamps.map((row, d) => row.reduce((a, b) => a + b, 0) + dayRewards[d]);
    const total = dayVisits.reduce((a, b) => a + b, 0);

    if (total === 0) return null;

    const rewards = dayRewards.reduce((a, b) => a + b, 0);
    const rates = dayVisits.map((v, d) => (v >= MIN_VISITS_FOR_RATE ? dayRewards[d] / v : null));
    const judged = rates.map((r, d) => ({ r, d })).filter((x) => x.r !== null);
    const high = judged.reduce((a, b) => (b.r > a.r ? b : a), judged[0]);
    const low = judged.reduce((a, b) => (b.r < a.r ? b : a), judged[0]);
    const weekendVisits = dayVisits[5] + dayVisits[6];

    const facts = [
        ['Overall', pct(rewards / total), `${formatNumber(rewards)} of ${formatNumber(total)} visits`],
        ['Most redeemed on', high ? WEEKDAYS[high.d] : '–', high ? `${pct(high.r)} of visits` : 'not enough visits'],
        ['Least redeemed on', low ? WEEKDAYS[low.d] : '–', low ? `${pct(low.r)} of visits` : 'not enough visits'],
        ['Weekends', weekendVisits ? pct((dayRewards[5] + dayRewards[6]) / weekendVisits) : '–', 'of weekend visits'],
    ];

    return (
        <dl className="mt-5 grid grid-cols-2 gap-3 border-t border-brand-border pt-4 sm:grid-cols-4">
            {facts.map(([label, value, note]) => (
                <div key={label} className="min-w-0 rounded-xl bg-brand-bg px-3 py-2.5">
                    <dt className="text-[11px] font-medium uppercase tracking-wide text-brand-muted">{label}</dt>
                    <dd className="mt-0.5 truncate text-base font-bold text-brand-text">{value}</dd>
                    <dd className="truncate text-xs text-brand-muted">{note}</dd>
                </div>
            ))}
        </dl>
    );
}

function VisitsPanel({ visits }) {
    const [metric, setMetric] = useState('stamps');

    const descriptions = {
        stamps: 'Stamps given',
        rewards: 'Rewards redeemed',
        rate: 'Share of visits that were a reward redemption',
    };

    let heatmap;

    if (metric === 'rate') {
        const visitsIn = (d, h) => visits.stamps[d][h] + visits.rewards[d][h];
        const grid = visits.stamps.map((row, d) => row.map((_, h) => (visitsIn(d, h) >= MIN_VISITS_FOR_RATE ? visits.rewards[d][h] / visitsIn(d, h) : null)));

        heatmap = (
            <Heatmap
                grid={grid}
                ramp={BLUE_RAMP}
                format={pct}
                topLabel="Highest"
                nullNote={`Fewer than ${MIN_VISITS_FOR_RATE} visits`}
                emptyText="Not enough visits yet to work out a rate."
                describe={(d, h) =>
                    grid[d][h] === null
                        ? `${slotLabel(d, h)}: ${visitsIn(d, h)} visits - too few to judge`
                        : `${slotLabel(d, h)}: ${pct(grid[d][h])} of ${formatNumber(visitsIn(d, h))} visits were redemptions (${formatNumber(visits.rewards[d][h])})`
                }
            >
                <RateSummary visits={visits} />
            </Heatmap>
        );
    } else {
        const unit = metric === 'stamps' ? 'stamps' : 'rewards';

        heatmap = (
            <Heatmap grid={visits[metric]} unit={unit} ramp={metric === 'rewards' ? BLUE_RAMP : MINT_RAMP} emptyText={`No ${unit} in this period yet.`}>
                <PeakSummary grid={visits[metric]} unit={unit} />
            </Heatmap>
        );
    }

    return (
        <FilteredPanel prop="visits" range={visits.range} title="When customers visit" description={`${descriptions[metric]} by weekday and hour, ${lastDays(visits.range)}`}>
            <div className="mb-4">
                <Segmented options={VISIT_METRICS} value={metric} onChange={setMetric} label="What to show" />
            </div>
            <div key={metric}>{heatmap}</div>
        </FilteredPanel>
    );
}

/* ---------- This period vs previous ---------- */

function PeriodComparison({ comparison }) {
    const { current, previous, range } = comparison;
    const perActive = (m) => (m.active_customers ? Math.round((m.stamps / m.active_customers) * 10) / 10 : null);

    const rows = [
        ['New customers', 'new_customers'],
        ['Active customers', 'active_customers'],
        ['· first-time', 'first_time_customers'],
        ['· returning', 'returning_customers'],
        ['Active shops', 'active_shops'],
        ['New shops', 'new_shops'],
        ['New owners', 'new_owners'],
        ['Stamps', 'stamps'],
        ['Rewards redeemed', 'rewards'],
        ['Reviews', 'reviews'],
        ['QR stickers mapped', 'qr_mapped'],
    ];

    return (
        <FilteredPanel prop="comparison" range={range} title="This period vs previous" description={`Last ${range} days vs the ${range} days before`} bodyClassName="">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-brand-border text-left text-xs uppercase tracking-wide text-brand-muted">
                        <th className="py-2.5 pl-5 font-medium">Metric</th>
                        <th className="px-2 py-2.5 text-right font-medium">Now</th>
                        <th className="px-2 py-2.5 text-right font-medium">Before</th>
                        <th className="py-2.5 pr-5 text-right font-medium">Change</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brand-border">
                    {rows.map(([label, key]) => (
                        <tr key={key}>
                            <td className={`py-2 ${label.startsWith('·') ? 'pl-9 text-brand-muted' : 'pl-5 text-brand-text'}`}>{label.replace('· ', '')}</td>
                            <td className="px-2 py-2 text-right font-semibold tabular-nums text-brand-text">{formatNumber(current[key])}</td>
                            <td className="px-2 py-2 text-right tabular-nums text-brand-muted">{formatNumber(previous[key])}</td>
                            <td className="py-2 pr-5 text-right">
                                <ChangePill change={percentChange(current[key], previous[key])} />
                            </td>
                        </tr>
                    ))}
                    <tr>
                        <td className="py-2 pl-5 text-brand-text">Stamps per active customer</td>
                        <td className="px-2 py-2 text-right font-semibold tabular-nums text-brand-text">{perActive(current) ?? '–'}</td>
                        <td className="px-2 py-2 text-right tabular-nums text-brand-muted">{perActive(previous) ?? '–'}</td>
                        <td className="py-2 pr-5 text-right">
                            <ChangePill change={perActive(previous) ? percentChange(perActive(current) ?? 0, perActive(previous)) : null} />
                        </td>
                    </tr>
                    <tr>
                        <td className="py-2 pl-5 text-brand-text">Avg review rating</td>
                        <td className="px-2 py-2 text-right font-semibold tabular-nums text-brand-text">{current.avg_rating ?? '–'}</td>
                        <td className="px-2 py-2 text-right tabular-nums text-brand-muted">{previous.avg_rating ?? '–'}</td>
                        <td className="py-2 pr-5 text-right text-xs font-semibold tabular-nums text-brand-muted">
                            {current.avg_rating !== null && previous.avg_rating !== null
                                ? `${current.avg_rating - previous.avg_rating >= 0 ? '+' : ''}${(current.avg_rating - previous.avg_rating).toFixed(1)} ★`
                                : '–'}
                        </td>
                    </tr>
                </tbody>
            </table>
        </FilteredPanel>
    );
}

/* ---------- Shops compared (sortable) ---------- */

const SHOP_COLUMNS = [
    { key: 'customers', label: 'Customers' },
    { key: 'new_customers', label: 'New' },
    { key: 'stamps', label: 'Stamps' },
    { key: 'change', label: 'Change' },
    { key: 'rewards', label: 'Rewards' },
    { key: 'rating', label: 'Rating' },
];

function ShopComparison({ shops: { rows: shops, range } }) {
    const [sort, setSort] = useState({ key: 'stamps', dir: 'desc' });

    const rows = useMemo(() => {
        const withChange = shops.map((s) => ({ ...s, change: percentChange(s.stamps, s.previous) }));
        const val = (row) => row[sort.key] ?? -Infinity;

        return withChange.sort((a, b) => (sort.dir === 'desc' ? val(b) - val(a) : val(a) - val(b)));
    }, [shops, sort]);

    const toggle = (key) => setSort((s) => ({ key, dir: s.key === key && s.dir === 'desc' ? 'asc' : 'desc' }));

    return (
        <FilteredPanel
            prop="shops"
            range={range}
            title="Shops compared"
            description={`The ${shops.length} busiest shops, ${lastDays(range)}, vs the ${range} days before. Click a column to sort.`}
            bodyClassName=""
            action={
                <Link href="/admin" className={panelLink}>
                    All shops <LuArrowRight className="h-4 w-4" />
                </Link>
            }
        >
            {shops.length === 0 ? (
                <EmptyState icon={LuTrophy} title="No stamps in this period">
                    Shops appear here once their staff start scanning.
                </EmptyState>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left">
                        <thead>
                            <tr className="border-b border-brand-border text-xs uppercase tracking-wide text-brand-muted">
                                <th className="py-3 pl-5 font-medium">Shop</th>
                                {SHOP_COLUMNS.map((col) => (
                                    <th key={col.key} className="px-3 py-3 text-right font-medium" aria-sort={sort.key === col.key ? (sort.dir === 'desc' ? 'descending' : 'ascending') : 'none'}>
                                        <button type="button" onClick={() => toggle(col.key)} className="inline-flex items-center gap-1 uppercase hover:text-brand-text">
                                            {col.label}
                                            {sort.key === col.key && (sort.dir === 'desc' ? <LuArrowDown className="h-3 w-3" /> : <LuArrowUp className="h-3 w-3" />)}
                                        </button>
                                    </th>
                                ))}
                                <th className="py-3 pr-5 text-right font-medium">Last scan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-brand-border">
                            {rows.map((shop) => (
                                <tr key={shop.id} className="transition-colors hover:bg-brand-bg/60">
                                    <td className="max-w-60 py-3 pl-5">
                                        <a href={`/s/${shop.slug}`} target="_blank" rel="noopener noreferrer" className="group block min-w-0">
                                            <span className="flex items-center gap-1.5 truncate text-sm font-semibold text-brand-text group-hover:underline">
                                                {shop.name}
                                                <LuExternalLink className="h-3.5 w-3.5 shrink-0 text-brand-muted opacity-0 group-hover:opacity-100" />
                                            </span>
                                            <span className="block truncate text-xs text-brand-muted">{shop.owner_name ?? 'No owner'}</span>
                                        </a>
                                    </td>
                                    <td className="px-3 py-3 text-right text-sm tabular-nums text-brand-text">{formatNumber(shop.customers)}</td>
                                    <td className="px-3 py-3 text-right text-sm tabular-nums text-brand-muted">+{formatNumber(shop.new_customers)}</td>
                                    <td className="px-3 py-3 text-right text-sm font-semibold tabular-nums text-brand-text">{formatNumber(shop.stamps)}</td>
                                    <td className="px-3 py-3 text-right">
                                        <ChangePill change={shop.change} />
                                    </td>
                                    <td className="px-3 py-3 text-right text-sm tabular-nums text-brand-text">{formatNumber(shop.rewards)}</td>
                                    <td className="px-3 py-3 text-right text-sm tabular-nums text-brand-text">
                                        {shop.rating === null ? <span className="text-brand-muted">–</span> : `${shop.rating} ★`}
                                        {shop.reviews > 0 && <span className="ml-1 text-xs text-brand-muted">({shop.reviews})</span>}
                                    </td>
                                    <td className="whitespace-nowrap py-3 pr-5 text-right text-xs text-brand-muted">{shop.last_activity ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </FilteredPanel>
    );
}

/* ---------- Ratings, health, activation ---------- */

function RatingsPanel({ ratings }) {
    return (
        <Panel title="Ratings" description="Each shop's own average, averaged - every shop counts equally.">
            {ratings.reviews === 0 ? (
                <EmptyState icon={LuStar} title="No ratings yet">
                    Customers rate their visit from their loyalty card.
                </EmptyState>
            ) : (
                <>
                    <div className="flex items-baseline gap-2">
                        <p className="text-3xl font-bold text-brand-text">{ratings.shop_average} ★</p>
                        <p className="text-xs text-brand-muted">
                            across {formatNumber(ratings.rated_shops)} shops · {formatNumber(ratings.reviews)} reviews
                        </p>
                    </div>

                    <div className="mt-4">
                        <BarList items={ratings.distribution.map((d) => ({ label: `${d.stars} ★`, value: d.count }))} of={ratings.reviews} />
                    </div>

                    <div className="mt-5 border-t border-brand-border pt-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">Lowest rated</p>
                        {ratings.lowest.length === 0 ? (
                            <p className="mt-2 text-sm text-brand-muted">No shop has {ratings.min_reviews}+ reviews yet.</p>
                        ) : (
                            <ul className="mt-2 space-y-2">
                                {ratings.lowest.map((shop) => (
                                    <li key={shop.id} className="flex items-center justify-between gap-2 text-sm">
                                        <span className="min-w-0 truncate text-brand-text">{shop.name}</span>
                                        <span className="shrink-0 tabular-nums">
                                            <span className="font-semibold text-brand-text">{shop.average} ★</span>
                                            <span className="ml-1 text-xs text-brand-muted">({shop.reviews})</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </>
            )}
        </Panel>
    );
}

function HealthMeter({ label, value, note }) {
    return (
        <li>
            <div className="flex items-baseline justify-between gap-2">
                <span className="text-sm text-brand-text">{label}</span>
                <span className="text-sm font-semibold tabular-nums text-brand-text">{value === null ? '–' : `${value}%`}</span>
            </div>
            <div className="mt-1.5 h-1.5 overflow-hidden rounded-full" style={{ backgroundColor: MINT_RAMP[1] }}>
                <div className="h-full rounded-full" style={{ width: `${value ?? 0}%`, backgroundColor: SERIES.primary }} />
            </div>
            <p className="mt-1 text-xs text-brand-muted">{note}</p>
        </li>
    );
}

function HealthPanel({ health }) {
    return (
        <Panel title="Platform health" description="All time - how much the app is really used.">
            <ul className="space-y-4">
                <HealthMeter label="Repeat customers" value={health.repeat_rate} note="Came back for at least a 2nd stamp" />
                <HealthMeter label="Reached a reward" value={health.reward_reach_rate} note="Cards that have redeemed at least once" />
                <HealthMeter label="Multi-shop customers" value={health.multi_shop_rate} note="Carry cards at 2 or more shops" />
                <HealthMeter label="Marketing opt-in" value={health.marketing_opt_in_rate} note="Cards that agreed to texts from their shop" />
                <HealthMeter label="Owners using Google" value={health.google_owner_rate} note="Signed up or log in with Google" />
            </ul>
        </Panel>
    );
}

/**
 * Owner activation: of the owners who joined in the chosen period, how far
 * they got. Each bar is the same group of people, so every drop-off is real.
 */
function ActivationPanel({ funnel }) {
    const signedUp = funnel.steps[0].value;
    const { owners, shops, stamping } = funnel.all_time;

    return (
        <FilteredPanel prop="funnel" range={funnel.range} title="Owner activation" description={`Owners who joined in the ${lastDays(funnel.range)}, and how far they've got.`}>
            {signedUp === 0 ? (
                <EmptyState icon={LuUserPlus} title="No new owners in this period">
                    Try a longer range to see how recent sign-ups are doing.
                </EmptyState>
            ) : (
                <>
                    <BarList items={funnel.steps} />
                    {funnel.median_days_to_first_stamp !== null && (
                        <div className="mt-5 rounded-xl bg-brand-bg px-4 py-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">Typical time to first stamp</p>
                            <p className="mt-0.5 text-lg font-bold text-brand-text">
                                {funnel.median_days_to_first_stamp === 0
                                    ? 'Same day'
                                    : `${funnel.median_days_to_first_stamp} ${funnel.median_days_to_first_stamp === 1 ? 'day' : 'days'}`}
                            </p>
                            <p className="text-xs text-brand-muted">from sign-up to the shop's first stamp (median)</p>
                        </div>
                    )}
                </>
            )}
            <p className="mt-4 border-t border-brand-border pt-3 text-xs text-brand-muted">
                A big drop after "Set up their shop" usually means the counter QR isn't up yet. All time: {formatNumber(owners)} owners ·{' '}
                {formatNumber(shops)} shops · {formatNumber(stamping)} have given stamps.
            </p>
        </FilteredPanel>
    );
}

/* ---------- People & housekeeping ---------- */

function RecentOwners({ owners }) {
    return (
        <Panel title="Newest owners" bodyClassName="">
            {owners.length === 0 ? (
                <EmptyState icon={LuUserPlus} title="No owners yet">
                    Owners appear here as they sign up or are added.
                </EmptyState>
            ) : (
                <ul className="divide-y divide-brand-border">
                    {owners.map((owner) => (
                        <li key={owner.id} className="flex items-center gap-3 px-5 py-3">
                            <Avatar name={owner.name} />
                            <div className="min-w-0 flex-1">
                                <p className="flex items-center gap-1.5 truncate text-sm font-medium text-brand-text">
                                    {owner.name}
                                    {owner.via_google && <FcGoogle className="h-3.5 w-3.5 shrink-0" title="Signed up with Google" />}
                                </p>
                                <p className="truncate text-xs text-brand-muted">
                                    {owner.shop_name ?? <span className="font-medium text-amber-700">Setup not finished</span>} · {owner.joined}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function QuietShops({ shops, days }) {
    return (
        <Panel title="Needs attention" description={`No stamps in the last ${days} days.`} bodyClassName="">
            {shops.length === 0 ? (
                <EmptyState icon={LuStamp} title="Every shop is active">
                    Nobody has gone quiet for {days} days or more.
                </EmptyState>
            ) : (
                <ul className="divide-y divide-brand-border">
                    {shops.map((shop) => (
                        <li key={shop.id} className="flex items-center gap-3 px-5 py-3">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-bg text-brand-muted">
                                <LuMoon className="h-4 w-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-brand-text">{shop.name}</p>
                                <p className="truncate text-xs text-brand-muted">
                                    {shop.owner_name ?? 'No owner'} · {shop.last_activity ? `last scan ${shop.last_activity}` : 'never scanned'}
                                </p>
                            </div>
                            <a
                                href={`/s/${shop.slug}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`Open ${shop.name}'s customer page`}
                                className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg hover:text-brand-text"
                            >
                                <LuExternalLink className="h-4 w-4" />
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

const STAGE_TONE = {
    received: 'bg-amber-500/10 text-amber-700',
    processing: 'bg-sky-500/10 text-sky-700',
    dispatched: 'bg-violet-500/10 text-violet-700',
    delivered: 'bg-emerald-500/10 text-emerald-700',
};

/** One small figure in the orders card. `alert` highlights work waiting to be done. */
function OrderFigure({ icon: Icon, label, value, note, alert = false }) {
    return (
        <div className={`rounded-xl px-4 py-3 ${alert ? 'bg-amber-500/10 ring-1 ring-inset ring-amber-500/30' : 'bg-brand-bg'}`}>
            <p className={`flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide ${alert ? 'text-amber-800' : 'text-brand-muted'}`}>
                <Icon className="h-3.5 w-3.5" /> {label}
            </p>
            <p className="mt-1 text-2xl font-bold tabular-nums text-brand-text">{value}</p>
            {note && <p className="text-xs text-brand-muted">{note}</p>}
        </div>
    );
}

/** First thing on the dashboard: any orders to post, and the latest ones. */
function OrdersPanel({ orders }) {
    return (
        <Panel
            title="Orders"
            description={orders.to_do > 0 ? `${orders.to_do} waiting to be posted.` : 'Nothing waiting to be posted.'}
            action={
                <Link href="/admin/orders" className={panelLink}>
                    All orders <LuArrowRight className="h-4 w-4" />
                </Link>
            }
        >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <OrderFigure icon={LuPackage} label="To post" value={formatNumber(orders.to_do)} note="Received or processing" alert={orders.to_do > 0} />
                <OrderFigure icon={LuTruck} label="On the way" value={formatNumber(orders.on_the_way)} note="Dispatched, not delivered" />
                <OrderFigure
                    icon={LuPackageCheck}
                    label="Last 7 days"
                    value={formatNumber(orders.week_count)}
                    note={`${formatPence(orders.week_revenue_pence)} paid`}
                />
            </div>

            {orders.recent.length === 0 ? (
                <EmptyState icon={LuPackage} title="No orders yet">
                    Orders show up here when an owner pays online, or when you record one on a shop's page.
                </EmptyState>
            ) : (
                <ul className="-mx-5 -mb-5 mt-4 divide-y divide-brand-border border-t border-brand-border">
                    {orders.recent.map((order) => {
                        const step = order.steps.find((s) => s.key === order.stage);

                        return (
                            <li key={order.id}>
                                <Link
                                    href={`/admin/shops/${order.shop_id}/settings`}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-sm transition-colors hover:bg-brand-bg"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium text-brand-text">{order.shop_name}</span>
                                        <span className="block truncate text-xs text-brand-muted">
                                            #{order.id} · {order.quantity} × {order.product_name} · {order.payment_label}
                                        </span>
                                    </span>
                                    <span className="tabular-nums text-brand-text">{formatPence(order.total_pence)}</span>
                                    <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${STAGE_TONE[order.stage]}`}>
                                        {step?.label}
                                    </span>
                                    <span className="w-20 text-right text-xs text-brand-muted">{order.steps[0].date}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

function QrPanel({ qr }) {
    const percent = qr.total ? Math.round((qr.mapped / qr.total) * 100) : 0;

    return (
        <Panel
            title="QR stickers"
            action={
                <Link href="/admin/qr-codes" className={panelLink}>
                    Manage <LuArrowRight className="h-4 w-4" />
                </Link>
            }
        >
            {qr.total === 0 ? (
                <EmptyState icon={LuQrCode} title="No QR codes yet">
                    Generate a batch of stickers from the QR codes page.
                </EmptyState>
            ) : (
                <div>
                    <p className="text-3xl font-bold text-brand-text">
                        {formatNumber(qr.mapped)}
                        <span className="text-lg font-medium text-brand-muted"> / {formatNumber(qr.total)}</span>
                    </p>
                    <p className="mt-1 text-sm text-brand-muted">stickers mapped to a destination</p>
                    <div className="mt-4 h-2 overflow-hidden rounded-full" style={{ backgroundColor: MINT_RAMP[1] }}>
                        <div className="h-full rounded-full" style={{ width: `${percent}%`, backgroundColor: SERIES.primary }} />
                    </div>
                    <div className="mt-2 flex justify-between text-xs text-brand-muted">
                        <span>{percent}% mapped</span>
                        <span>{formatNumber(qr.total - qr.mapped)} unmapped</span>
                    </div>
                </div>
            )}
        </Panel>
    );
}

/* ---------- Page ---------- */

export default function Dashboard({
    ranges,
    orders,
    kpis,
    customerGrowth,
    shopGrowth,
    activeCustomers,
    stampsRewards,
    visits,
    comparison,
    shops,
    funnel,
    ratings,
    health,
    qr,
    recentOwners,
    quietShops,
    quietDays,
}) {
    // Which card (prop) is reloading. A card's range change reloads only that
    // prop; while it loads, that card keeps its previous render, dimmed.
    const [loading, setLoading] = useState(null);

    // router.reload always keeps scroll position and page state.
    const reload = (prop, range) =>
        router.reload({
            only: [prop],
            data: { [prop]: range },
            onStart: () => setLoading(prop),
            onFinish: () => setLoading(null),
        });

    const { current, previous, totals, rating } = kpis;
    const kpiBefore = (value) => `vs ${formatNumber(value)} the ${kpis.range} days before`;

    return (
        <DashboardContext.Provider value={{ ranges, loading, reload }}>
            <AdminLayout title="Dashboard" description="Growth, popularity and health across every shop. Each chart has its own time range.">
                <OrdersPanel orders={orders} />

                {/* KPIs - compact, each with its change vs the previous period */}
                <SectionTitle
                    hint={`${lastDays(kpis.range)} vs the ${kpis.range} days before`}
                    action={<RangeTabs ranges={ranges} value={kpis.range} busy={loading === 'kpis'} onChange={(r) => reload('kpis', r)} />}
                >
                    Key numbers
                </SectionTitle>
                <div className={`grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-6 ${loading === 'kpis' ? 'opacity-60' : ''}`}>
                    <KpiTile
                        icon={LuUsers}
                        label="New customers"
                        value={`+${formatNumber(current.new_customers)}`}
                        current={current.new_customers}
                        previous={previous.new_customers}
                        note={`${formatNumber(totals.customers)} in total`}
                    />
                    <KpiTile
                        icon={LuActivity}
                        label="Active customers"
                        value={formatNumber(current.active_customers)}
                        current={current.active_customers}
                        previous={previous.active_customers}
                        note={kpiBefore(previous.active_customers)}
                    />
                    <KpiTile
                        icon={LuStore}
                        label="Active shops"
                        value={formatNumber(current.active_shops)}
                        current={current.active_shops}
                        previous={previous.active_shops}
                        note={`of ${formatNumber(totals.shops)} · +${formatNumber(current.new_shops)} new`}
                    />
                    <KpiTile
                        icon={LuStamp}
                        label="Stamps"
                        value={formatNumber(current.stamps)}
                        current={current.stamps}
                        previous={previous.stamps}
                        note={kpiBefore(previous.stamps)}
                    />
                    <KpiTile
                        icon={LuGift}
                        label="Rewards redeemed"
                        value={formatNumber(current.rewards)}
                        current={current.rewards}
                        previous={previous.rewards}
                        note={kpiBefore(previous.rewards)}
                    />
                    <KpiTile
                        icon={LuStar}
                        label="Avg shop rating"
                        value={rating.shop_average === null ? '–' : `${rating.shop_average} ★`}
                        note={`${formatNumber(rating.rated_shops)} rated shops · all time`}
                    />
                </div>

                {/* Growth */}
                <SectionTitle hint="Running total above, new each day below - same days, own scale each">Growth</SectionTitle>
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    <FilteredPanel
                        prop="customerGrowth"
                        range={customerGrowth.range}
                        className="xl:col-span-2"
                        title="Customers"
                        description={`${formatNumber(customerGrowth.total)} in total · +${formatNumber(customerGrowth.new)} in the ${lastDays(customerGrowth.range)}`}
                    >
                        <StackedLines
                            data={customerGrowth.days}
                            caption="Customers per day"
                            lines={[
                                { key: 'total', label: 'Total customers', color: SERIES.primary, height: 150, area: true },
                                { key: 'new', label: 'New customers per day', color: SERIES.secondary, height: 80 },
                            ]}
                        />
                    </FilteredPanel>
                    <FilteredPanel
                        prop="shopGrowth"
                        range={shopGrowth.range}
                        title="Shops"
                        description={`${formatNumber(shopGrowth.total)} in total · +${formatNumber(shopGrowth.new)} in the ${lastDays(shopGrowth.range)}`}
                    >
                        <StackedLines
                            data={shopGrowth.days}
                            caption="Shops per day"
                            lines={[
                                { key: 'total', label: 'Total shops', color: SERIES.primary, height: 150, area: true },
                                { key: 'new', label: 'New shops per day', color: SERIES.secondary, height: 80 },
                            ]}
                        />
                    </FilteredPanel>
                </div>

                {/* Popularity */}
                <SectionTitle hint="How much the app is used each day">Popularity</SectionTitle>
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <FilteredPanel
                        prop="activeCustomers"
                        range={activeCustomers.range}
                        title="Active customers"
                        description={`${formatNumber(activeCustomers.returning)} returning · ${formatNumber(activeCustomers.first_time)} first-time, ${lastDays(activeCustomers.range)}`}
                    >
                        <ColumnChart
                            data={activeCustomers.days}
                            caption="Active customers per day, returning and first visit"
                            series={[
                                { key: 'returning', label: 'Returning', color: SERIES.primary },
                                { key: 'first_time', label: 'First visit', color: SERIES.secondary },
                            ]}
                        />
                    </FilteredPanel>
                    <FilteredPanel
                        prop="stampsRewards"
                        range={stampsRewards.range}
                        title="Stamps & rewards"
                        description={`${formatNumber(stampsRewards.stamps)} stamps · ${formatNumber(stampsRewards.rewards)} rewards, ${lastDays(stampsRewards.range)}`}
                    >
                        <ColumnChart
                            data={stampsRewards.days}
                            caption="Stamps and rewards redeemed per day"
                            series={[
                                { key: 'stamps', label: 'Stamps', color: SERIES.primary },
                                { key: 'rewards', label: 'Rewards redeemed', color: SERIES.secondary },
                            ]}
                        />
                    </FilteredPanel>
                </div>

                {/* Compare */}
                <SectionTitle hint="When the app is used, and how a period compares with the one before">Compare</SectionTitle>
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-5">
                    <div className="xl:col-span-3">
                        <VisitsPanel visits={visits} />
                    </div>
                    <div className="xl:col-span-2">
                        <PeriodComparison comparison={comparison} />
                    </div>
                </div>

                {/* Shops */}
                <SectionTitle>Shops & owners</SectionTitle>
                <ShopComparison shops={shops} />

                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <RatingsPanel ratings={ratings} />
                    <HealthPanel health={health} />
                    <ActivationPanel funnel={funnel} />
                </div>

                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <RecentOwners owners={recentOwners} />
                    <QuietShops shops={quietShops} days={quietDays} />
                    <QrPanel qr={qr} />
                </div>
            </AdminLayout>
        </DashboardContext.Provider>
    );
}
