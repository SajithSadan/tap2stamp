import { router } from '@inertiajs/react';
import { createContext, useContext, useState } from 'react';
import { LuCalendarClock, LuGift, LuLightbulb, LuMessageCircle, LuRepeat, LuTrendingDown, LuTrophy, LuUserCheck } from 'react-icons/lu';
import { BarList, ColumnChart, Heatmap, KpiTile, PeakSummary, RangeTabs } from '@/Components/Dashboard/Charts';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { Avatar, EmptyState, Panel } from '@/Components/Dashboard/Ui';
import { SERIES } from '@/lib/charts';

const InsightsContext = createContext(null);

const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

/** 4.6 → "about every 5 days"; ~1 → "almost every day". */
function rhythm(days) {
    if (days === null || days === undefined) return null;
    if (days < 1.5) return 'almost every day';

    return `about every ${Math.round(days)} days`;
}

function dueLabel(n) {
    if (n < 0) return `${plural(-n, 'day')} late`;
    if (n === 0) return 'Due today';
    if (n === 1) return 'Due tomorrow';

    return `Due in ${n} days`;
}

/** A Panel with its own period switch; switching reloads only this section. */
function FilteredPanel({ prop, range, children, className = '', ...panelProps }) {
    const { ranges, loading, reload } = useContext(InsightsContext);

    return (
        <Panel
            {...panelProps}
            className={`transition-opacity ${loading === prop ? 'opacity-60' : ''} ${className}`}
            action={<RangeTabs ranges={ranges} value={range} busy={loading === prop} onChange={(r) => reload(prop, r)} />}
        >
            {children}
        </Panel>
    );
}

function SectionTitle({ children, hint }) {
    return (
        <div className="mb-3 mt-8 flex flex-wrap items-baseline gap-x-3 gap-y-1 first:mt-0">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-brand-muted">{children}</h2>
            {hint && <p className="text-xs text-brand-muted">{hint}</p>}
        </div>
    );
}

/** One customer: name, opted-in mark, phone, a line about them, and a figure on the right. */
function CustomerRow({ customer, rank, meta, figure, figureNote }) {
    return (
        <li className="flex items-center gap-3 px-5 py-3">
            {rank !== undefined && <span className="w-5 shrink-0 text-right text-sm font-semibold tabular-nums text-brand-muted">{rank}</span>}
            <Avatar name={customer.name} />
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-1.5 truncate font-medium text-brand-text">
                    <span className="truncate">{customer.name}</span>
                    {customer.marketing_consent && (
                        <LuMessageCircle className="h-3.5 w-3.5 shrink-0 text-emerald-600" title="Happy to get messages from you" aria-label="Opted in to messages" />
                    )}
                </p>
                <p className="break-words text-xs text-brand-muted">
                    {customer.phone && (
                        <a href={`tel:${customer.phone}`} className="hover:text-brand-text">
                            {customer.phone}
                        </a>
                    )}
                    {customer.phone && meta && ' · '}
                    {meta}
                </p>
            </div>
            {figure !== undefined && (
                <div className="shrink-0 text-right">
                    <p className="text-sm font-semibold tabular-nums text-brand-text">{figure}</p>
                    {figureNote && <p className="text-xs text-brand-muted">{figureNote}</p>}
                </div>
            )}
        </li>
    );
}

const FIRST_ROWS = 5;

/**
 * A customer list that starts with the first few rows and opens to the
 * rest. `total` may be more than the rows sent (the server sends up to 25).
 */
function ShortList({ items, total = items.length, children, ordered = false }) {
    const [open, setOpen] = useState(false);
    const shown = open ? items : items.slice(0, FIRST_ROWS);
    const List = ordered ? 'ol' : 'ul';

    return (
        <>
            <List className="divide-y divide-brand-border">{shown.map(children)}</List>
            {(items.length > FIRST_ROWS || total > items.length) && (
                <div className="flex items-center justify-between gap-3 border-t border-brand-border px-5 py-2.5 text-xs">
                    {items.length > FIRST_ROWS ? (
                        <button type="button" onClick={() => setOpen(!open)} className="font-semibold text-brand-accent hover:underline">
                            {open ? 'Show fewer' : `Show all ${items.length}`}
                        </button>
                    ) : (
                        <span />
                    )}
                    {total > items.length && <span className="text-brand-muted">{total - items.length} more not shown</span>}
                </div>
            )}
        </>
    );
}

/* ---------- 1. Regulars and rewards ---------- */

function Regulars({ regulars }) {
    return (
        <FilteredPanel
            prop="regulars"
            range={regulars.range}
            className="xl:col-span-3"
            title="Your regulars"
            description={`Most visits in the last ${regulars.range} days · ${plural(regulars.active_customers, 'customer')} visited`}
            bodyClassName=""
        >
            {regulars.customers.length === 0 ? (
                <EmptyState icon={LuTrophy} title="No visits in this period yet" />
            ) : (
                <ol className="divide-y divide-brand-border">
                    {regulars.customers.map((c, i) => (
                        <CustomerRow
                            key={c.id}
                            customer={c}
                            rank={i + 1}
                            meta={[rhythm(c.every_days), c.last_visit && `last ${c.last_visit}`].filter(Boolean).join(' · ')}
                            figure={plural(c.visits, 'visit')}
                            figureNote={c.rewards > 0 ? plural(c.rewards, 'reward') : null}
                        />
                    ))}
                </ol>
            )}
        </FilteredPanel>
    );
}

function CloseToReward({ rewards }) {
    const empty = rewards.ready.length === 0 && rewards.almost.length === 0;

    return (
        <Panel className="xl:col-span-2" title="Close to a reward" description="Worth a word next time they're in." bodyClassName="">
            {empty ? (
                <EmptyState icon={LuGift} title="Nobody's close to a reward yet" />
            ) : (
                <>
                    {rewards.ready.length > 0 && (
                        <>
                            <p className="bg-brand-bg/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-brand-muted">
                                Reward ready to claim · {rewards.ready.length}
                            </p>
                            <ShortList items={rewards.ready}>
                                {(c) => <CustomerRow key={c.id} customer={c} meta={c.last_visit && `last ${c.last_visit}`} figure={<LuGift className="ml-auto h-4 w-4 text-brand-accent" />} />}
                            </ShortList>
                        </>
                    )}
                    {rewards.almost.length > 0 && (
                        <>
                            <p className="bg-brand-bg/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-brand-muted">
                                1–2 stamps to go · {rewards.almost.length}
                            </p>
                            <ShortList items={rewards.almost}>
                                {(c) => (
                                    <CustomerRow
                                        key={c.id}
                                        customer={c}
                                        meta={c.last_visit && `last ${c.last_visit}`}
                                        figure={`${c.stamps}/${rewards.max_stamps}`}
                                        figureNote={`${c.to_go} to go`}
                                    />
                                )}
                            </ShortList>
                        </>
                    )}
                </>
            )}
        </Panel>
    );
}

/* ---------- 2. Due back / drifting away ---------- */

function DueBack({ dueBack }) {
    return (
        <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
            <Panel title="Due back this week" description="Regulars whose usual visit falls in the next 7 days." bodyClassName="">
                {dueBack.due.length === 0 ? (
                    <EmptyState icon={LuCalendarClock} title="Nobody due this week">
                        Customers show here once they've visited at least 3 times.
                    </EmptyState>
                ) : (
                    <ShortList items={dueBack.due} total={dueBack.due_count}>
                        {(c) => (
                            <CustomerRow
                                key={c.id}
                                customer={c}
                                meta={[rhythm(c.every_days), c.last_visit && `last ${c.last_visit}`].filter(Boolean).join(' · ')}
                                figure={dueLabel(c.due_in_days)}
                            />
                        )}
                    </ShortList>
                )}
            </Panel>

            <Panel title="Drifting away" description="Regulars now away more than twice as long as usual - your best customers first." bodyClassName="">
                {dueBack.drifting.length === 0 ? (
                    <EmptyState icon={LuTrendingDown} title="No regulars drifting away">
                        Good news - your regulars are keeping to their usual rhythm.
                    </EmptyState>
                ) : (
                    <ShortList items={dueBack.drifting} total={dueBack.drifting_count}>
                        {(c) => (
                            <CustomerRow
                                key={c.id}
                                customer={c}
                                meta={`usually ${rhythm(c.every_days)} · ${plural(c.visits, 'visit')}`}
                                figure={`${c.away_days} days`}
                                figureNote="away"
                            />
                        )}
                    </ShortList>
                )}
            </Panel>
        </div>
    );
}

/* ---------- 3. Is the card working? ---------- */

function Loyalty({ loyalty }) {
    const { second_visit: second, visits, cards } = loyalty;
    const stalled = cards.stalled.map((s) => ({ label: `Stopped at ${s.stamps} of ${cards.max_stamps}`, value: s.cards }));

    return (
        <FilteredPanel prop="loyalty" range={loyalty.range} title="Is your loyalty card working?" description={`Last ${loyalty.range} days`}>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <KpiTile
                    icon={LuRepeat}
                    label="Came back"
                    value={second.rate === null ? '–' : `${second.rate}%`}
                    note={second.customers ? `${second.came_back} of ${second.customers} new` : 'None to measure yet'}
                />
                <KpiTile
                    icon={LuUserCheck}
                    label="Returning"
                    value={visits.returning_share === null ? '–' : `${visits.returning_share}%`}
                    note={`${visits.returning} of ${visits.returning + visits.first} visits`}
                />
                <KpiTile
                    icon={LuGift}
                    label="Rewards"
                    value={cards.rewards}
                    note={cards.median_days_to_fill !== null ? `~${cards.median_days_to_fill} days a card` : 'None yet'}
                />
                <KpiTile
                    icon={LuTrophy}
                    label="Ever earned"
                    value={cards.earned_share === null ? '–' : `${cards.earned_share}%`}
                    note={`${cards.earned} of ${cards.customers}`}
                />
            </div>
            <p className="mt-3 text-xs text-brand-muted">
                <strong className="font-medium text-brand-text">Came back</strong> = new customers whose first visit was {second.from}–{second.to} and who visited again within{' '}
                {second.window_days} days. It's the best sign your card is bringing people back. <strong className="font-medium text-brand-text">Ever earned</strong> = customers who've earned at least one reward.
            </p>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-5">
                <div className="min-w-0 xl:col-span-3">
                    <h3 className="mb-3 text-sm font-semibold text-brand-text">
                        First visits and returning visits per {visits.per}
                        {visits.per === 'week' && <span className="font-normal text-brand-muted"> (weeks starting Monday)</span>}
                    </h3>
                    <ColumnChart
                        data={visits.rows}
                        caption={`Visits per ${visits.per}, returning and first visits`}
                        series={[
                            { key: 'returning', label: 'Returning', color: SERIES.primary },
                            { key: 'first', label: 'First visit', color: SERIES.secondary },
                        ]}
                    />
                </div>
                <div className="min-w-0 xl:col-span-2">
                    <h3 className="text-sm font-semibold text-brand-text">Where unfinished cards stall</h3>
                    <p className="mb-3 text-xs text-brand-muted">
                        {cards.stalled_total
                            ? `${plural(cards.stalled_total, 'card')} not stamped for ${cards.stalled_days}+ days, by where they stopped.`
                            : `No cards left unstamped for ${cards.stalled_days}+ days.`}
                    </p>
                    {cards.stalled_total > 0 && <BarList items={stalled} of={cards.stalled_total} />}
                    {cards.suggest_fewer && (
                        <p className="mt-4 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-950">
                            <LuLightbulb className="mt-0.5 h-4 w-4 shrink-0" />
                            Most unfinished cards stop early and few customers ever earn a reward. A card with fewer stamps (e.g. {Math.max(3, cards.max_stamps - 2)}{' '}
                            instead of {cards.max_stamps}) could bring more people back - you can change it in Settings.
                        </p>
                    )}
                </div>
            </div>
        </FilteredPanel>
    );
}

/* ---------- 4. Busy times ---------- */

/** First and last hour with any scans, padded by an hour - a café's day, not the whole 24. */
function activeHours(grid) {
    const used = Array.from({ length: 24 }, (_, h) => h).filter((h) => grid.some((row) => row[h] > 0));
    if (used.length === 0) return [7, 19];

    let [from, to] = [Math.max(0, used[0] - 1), Math.min(23, used[used.length - 1] + 1)];
    while (to - from < 7) {
        if (from > 0) from--;
        if (to - from < 7 && to < 23) to++;
    }

    return [from, to];
}

function BusyTimes({ busy }) {
    return (
        <FilteredPanel prop="busy" range={busy.range} title="When you're busy" description="Card scans by day and hour - handy for staffing and quiet-hour offers.">
            <Heatmap grid={busy.grid} hours={activeHours(busy.grid)} unit="scans" emptyText="No scans in this period yet.">
                <PeakSummary grid={busy.grid} unit="scans" />
            </Heatmap>
        </FilteredPanel>
    );
}

export default function Insights({ shop, ranges, regulars, rewards, dueBack, loyalty, busy }) {
    const [loading, setLoading] = useState(null);

    // Reload only this section, with its own period in the URL (?regulars=30).
    function reload(prop, range) {
        router.reload({
            only: [prop],
            data: { [prop]: range },
            onStart: () => setLoading(prop),
            onFinish: () => setLoading(null),
        });
    }

    return (
        <OwnerLayout shop={shop} title="Insights" description="Who your regulars are, who's due back, and how your loyalty card is doing.">
            <InsightsContext.Provider value={{ ranges, loading, reload }}>
                <SectionTitle hint={<span className="inline-flex items-center gap-1"><LuMessageCircle className="h-3.5 w-3.5 text-emerald-600" /> = happy to get messages from you</span>}>
                    Your customers
                </SectionTitle>
                <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-5">
                    <Regulars regulars={regulars} />
                    <CloseToReward rewards={rewards} />
                </div>

                <SectionTitle hint="From each regular's own visit pattern (3+ visits).">Who to expect</SectionTitle>
                <DueBack dueBack={dueBack} />

                <SectionTitle>Your loyalty card</SectionTitle>
                <Loyalty loyalty={loyalty} />

                <SectionTitle>Busy times</SectionTitle>
                <BusyTimes busy={busy} />
            </InsightsContext.Provider>
        </OwnerLayout>
    );
}
