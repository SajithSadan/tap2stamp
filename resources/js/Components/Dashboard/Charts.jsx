import { useLayoutEffect, useRef, useState } from 'react';
import { LuArrowDownRight, LuArrowUpRight, LuMinus } from 'react-icons/lu';
import { BASELINE, formatNumber, GRID, labelEvery, MINT_RAMP, niceMax, percentChange, SERIES } from '@/lib/charts';

// Dashboard chart kit. Rules followed throughout: one y-scale per plot (two
// measures = two stacked plots, never a dual axis), thin marks, solid
// hairline grid, a legend only for 2+ series, text in text colours (never
// the series colour), a hover/keyboard tooltip on every chart, a screen-
// reader table with the same numbers, and every label kept inside its chart.

/** Measures an element's width, following resizes (sidebar, rotation, window). */
function useElementWidth() {
    const ref = useRef(null);
    const [width, setWidth] = useState(0);

    useLayoutEffect(() => {
        if (!ref.current) return undefined;

        const observer = new ResizeObserver(([entry]) => setWidth(Math.floor(entry.contentRect.width)));
        observer.observe(ref.current);

        return () => observer.disconnect();
    }, []);

    return [ref, width];
}

function SrTable({ caption, columns, rows }) {
    return (
        <table className="sr-only">
            <caption>{caption}</caption>
            <thead>
                <tr>
                    {columns.map((c) => (
                        <th key={c} scope="col">
                            {c}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows.map((row, i) => (
                    <tr key={i}>
                        {row.map((cell, j) => (j === 0 ? <th key={j} scope="row">{cell}</th> : <td key={j}>{cell}</td>))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** Tooltip card: title, then rows keyed by a short line in the series colour; values lead. */
function TooltipCard({ title, rows }) {
    return (
        <span className="block w-max max-w-56 rounded-lg border border-brand-border bg-brand-card px-3 py-2 text-left shadow-lg">
            <span className="block text-xs font-medium text-brand-muted">{title}</span>
            {rows.map((row) => (
                <span key={row.label} className="mt-1 flex items-center gap-2">
                    {row.color && <span className="h-0.5 w-3 shrink-0 rounded-full" style={{ backgroundColor: row.color }} />}
                    <span className="text-sm font-semibold tabular-nums text-brand-text">{row.value}</span>
                    <span className="text-xs text-brand-muted">{row.label}</span>
                </span>
            ))}
        </span>
    );
}

/** Legend: swatch mirrors the mark (line key for lines, square for bars). */
export function Legend({ series, kind = 'bar' }) {
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-muted">
            {series.map((s) => (
                <span key={s.key} className="inline-flex items-center gap-1.5">
                    <span className={kind === 'line' ? 'h-0.5 w-3.5 rounded-full' : 'h-2.5 w-2.5 rounded-[3px]'} style={{ backgroundColor: s.color }} />
                    {s.label}
                </span>
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Compact KPI tile: value, change pill, and what it's compared with   */
/* ------------------------------------------------------------------ */

/** Signed change as a pill with an arrow + number, so it never relies on colour alone. */
export function ChangePill({ change }) {
    if (change === null || change === undefined) {
        return <span className="rounded-full bg-brand-bg px-2 py-0.5 text-[11px] font-medium text-brand-muted">New</span>;
    }

    const Icon = change === 0 ? LuMinus : change > 0 ? LuArrowUpRight : LuArrowDownRight;
    const tone = change === 0 ? 'bg-brand-bg text-brand-muted' : change > 0 ? 'bg-emerald-500/10 text-emerald-700' : 'bg-red-500/10 text-red-700';

    return (
        <span className={`inline-flex shrink-0 items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums ${tone}`}>
            <Icon className="h-3 w-3" />
            {change > 0 ? '+' : ''}
            {change}%
        </span>
    );
}

export function KpiTile({ icon: Icon, label, value, current, previous, note }) {
    const change = current === undefined ? undefined : percentChange(current, previous);

    return (
        <div className="min-w-0 rounded-2xl border border-brand-border bg-brand-card px-4 py-3.5 shadow-sm">
            <div className="flex items-center justify-between gap-2">
                <p className="truncate text-[11px] font-medium uppercase tracking-wide text-brand-muted">{label}</p>
                {Icon && <Icon className="h-4 w-4 shrink-0 text-brand-accent" />}
            </div>
            <div className="mt-1.5 flex items-center justify-between gap-2">
                <p className="truncate text-2xl font-bold text-brand-text">{value}</p>
                {change !== undefined && <ChangePill change={change} />}
            </div>
            <p className="mt-1 truncate text-xs text-brand-muted" title={note}>
                {note}
            </p>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Stacked lines: 2+ measures over the same days, each in its own band */
/* with its own scale, sharing the x-axis and one crosshair.          */
/* ------------------------------------------------------------------ */

const PAD = { top: 8, right: 16, bottom: 26, left: 44 };
const BAND_GAP = 28;

/** lines: [{ key, label, color, height, area?, format?(v), tickFormat?(v) }] */
export function StackedLines({ data, lines, caption }) {
    const [ref, width] = useElementWidth();
    const [active, setActive] = useState(null);
    const n = data.length;
    const plotW = Math.max(0, width - PAD.left - PAD.right);
    const x = (i) => PAD.left + (n === 1 ? plotW / 2 : (i / (n - 1)) * plotW);
    const every = labelEvery(n);

    // Lay the bands out top to bottom.
    let cursor = PAD.top;
    const bands = lines.map((line) => {
        const top = cursor + 16; // room for the band's own title
        const values = data.map((d) => d[line.key]);
        const max = niceMax(Math.max(0, ...values));
        cursor = top + line.height + BAND_GAP;
        // Optional per-line formatting (e.g. money); axis ticks may use a shorter form.
        const fmt = line.format ?? formatNumber;

        return { ...line, top, max, values, fmt, tickFmt: line.tickFormat ?? fmt, y: (v) => top + line.height - (v / max) * line.height };
    });
    const height = cursor - BAND_GAP + PAD.bottom;

    function pick(clientX, rect) {
        const ratio = (clientX - rect.left - PAD.left) / (plotW || 1);
        setActive(Math.min(n - 1, Math.max(0, Math.round(ratio * (n - 1)))));
    }

    function onKeyDown(e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            e.preventDefault();
            setActive((a) => Math.min(n - 1, Math.max(0, (a ?? n - 1) + (e.key === 'ArrowRight' ? 1 : -1))));
        }
    }

    const anchorFor = (i) => (i === n - 1 ? 'end' : x(i) - PAD.left < 24 ? 'start' : 'middle');
    const tipLeft = active !== null ? Math.min(Math.max(x(active), 110), width - 110) : 0;

    return (
        <div>
            <div className="mb-2">
                <Legend series={lines} kind="line" />
            </div>

            <div ref={ref} className="relative" style={{ height }}>
                {width > 0 && n > 0 && (
                    <svg
                        width={width}
                        height={height}
                        className="block touch-pan-y outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40"
                        tabIndex={0}
                        role="img"
                        aria-label={`${caption}. Use the left and right arrow keys to read each day.`}
                        onPointerMove={(e) => pick(e.clientX, e.currentTarget.getBoundingClientRect())}
                        onPointerLeave={() => setActive(null)}
                        onFocus={() => setActive(n - 1)}
                        onBlur={() => setActive(null)}
                        onKeyDown={onKeyDown}
                    >
                        {bands.map((band) => {
                            const path = band.values.map((v, i) => `${i ? 'L' : 'M'}${x(i)},${band.y(v)}`).join(' ');
                            const bottom = band.top + band.height;
                            const last = band.values[n - 1];

                            return (
                                <g key={band.key}>
                                    {/* Band title, in text ink - the line key beside it carries the identity */}
                                    <line x1={PAD.left} x2={PAD.left + 12} y1={band.top - 9} y2={band.top - 9} stroke={band.color} strokeWidth="2" strokeLinecap="round" />
                                    <text x={PAD.left + 18} y={band.top - 9} dy="0.32em" className="fill-brand-muted text-[11px] font-medium">
                                        {band.label}
                                    </text>

                                    {[band.max, band.max / 2, 0].map((t) => (
                                        <g key={t}>
                                            <line x1={PAD.left} x2={width - PAD.right} y1={band.y(t)} y2={band.y(t)} stroke={t === 0 ? BASELINE : GRID} strokeWidth="1" />
                                            <text x={PAD.left - 8} y={band.y(t)} dy="0.32em" textAnchor="end" className="fill-brand-muted text-[11px] tabular-nums">
                                                {band.tickFmt(t)}
                                            </text>
                                        </g>
                                    ))}

                                    {band.area && <path d={`${path} L${x(n - 1)},${bottom} L${x(0)},${bottom} Z`} fill={band.color} fillOpacity="0.1" />}
                                    <path d={path} fill="none" stroke={band.color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                                    <circle cx={x(n - 1)} cy={band.y(last)} r="4" fill={band.color} stroke="white" strokeWidth="2" />

                                    {/* Latest value, kept inside the band: left of the dot, never above the band top */}
                                    {active === null && (
                                        <text
                                            x={x(n - 1) - 8}
                                            y={Math.max(band.top + 10, band.y(last) - 10)}
                                            textAnchor="end"
                                            className="fill-brand-text text-xs font-semibold tabular-nums"
                                        >
                                            {band.fmt(last)}
                                        </text>
                                    )}

                                    {active !== null && <circle cx={x(active)} cy={band.y(band.values[active])} r="4.5" fill={band.color} stroke="white" strokeWidth="2" />}
                                </g>
                            );
                        })}

                        {active !== null && (
                            <line x1={x(active)} x2={x(active)} y1={PAD.top} y2={height - PAD.bottom} stroke={BASELINE} strokeWidth="1" pointerEvents="none" />
                        )}

                        {/* Shared x-axis, counted back from today; edge labels anchor inward so they never spill out */}
                        {data.map((d, i) =>
                            (n - 1 - i) % every === 0 ? (
                                <text key={d.date} x={x(i)} y={height - 8} textAnchor={anchorFor(i)} className="fill-brand-muted text-[10px]">
                                    {d.label}
                                </text>
                            ) : null,
                        )}
                    </svg>
                )}

                {active !== null && (
                    <span className="pointer-events-none absolute top-0 z-10 -translate-x-1/2" style={{ left: tipLeft }}>
                        <TooltipCard
                            title={`${data[active].weekday} ${data[active].label}`}
                            rows={bands.map((band) => ({ label: band.label.toLowerCase(), value: band.fmt(band.values[active]), color: band.color }))}
                        />
                    </span>
                )}
            </div>

            <SrTable caption={caption} columns={['Day', ...lines.map((l) => l.label)]} rows={data.map((d) => [d.label, ...bands.map((b) => b.fmt(d[b.key]))])} />
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Columns: one series, or 2 stacked                                  */
/* ------------------------------------------------------------------ */

export function ColumnChart({ data, series, caption, height = 180 }) {
    const [active, setActive] = useState(null);
    const n = data.length;
    const totals = data.map((d) => series.reduce((sum, s) => sum + d[s.key], 0));
    const top = niceMax(Math.max(0, ...totals));
    const ticks = [top, top / 2, 0];
    const every = labelEvery(n);

    return (
        <div>
            {series.length > 1 && (
                <div className="mb-3">
                    <Legend series={series} />
                </div>
            )}

            <div className="flex gap-2">
                <div className="flex w-8 shrink-0 flex-col justify-between text-right text-[11px] tabular-nums text-brand-muted" style={{ height }} aria-hidden="true">
                    {ticks.map((t) => (
                        <span key={t} className="-translate-y-1/2 leading-none first:translate-y-0 last:translate-y-0">
                            {formatNumber(t)}
                        </span>
                    ))}
                </div>

                <div className="relative min-w-0 flex-1">
                    <div className="pointer-events-none absolute inset-x-0 top-0 flex flex-col justify-between" style={{ height }} aria-hidden="true">
                        {ticks.map((t) => (
                            <div key={t} className="border-t" style={{ borderColor: t === 0 ? BASELINE : GRID }} />
                        ))}
                    </div>

                    <div className="relative flex items-end gap-[2px]" style={{ height }} onMouseLeave={() => setActive(null)}>
                        {data.map((d, i) => {
                            const total = totals[i];
                            const visible = series.filter((s) => d[s.key] > 0);

                            return (
                                <button
                                    key={d.date}
                                    type="button"
                                    onMouseEnter={() => setActive(i)}
                                    onFocus={() => setActive(i)}
                                    onBlur={() => setActive(null)}
                                    aria-label={`${d.label}: ${series.map((s) => `${d[s.key]} ${s.label.toLowerCase()}`).join(', ')}`}
                                    className="group relative flex h-full min-w-0 flex-1 items-end justify-center outline-none"
                                >
                                    {/* Capped width, 4px rounded top, 2px gap between stacked segments */}
                                    <span
                                        className={`flex w-full max-w-6 flex-col-reverse gap-[2px] overflow-hidden rounded-t-[4px] transition-opacity group-focus-visible:ring-2 group-focus-visible:ring-brand-accent/40 ${
                                            active !== null && active !== i ? 'opacity-40' : ''
                                        }`}
                                        style={{ height: `${(total / top) * 100}%` }}
                                    >
                                        {visible.map((s) => (
                                            <span key={s.key} className="block w-full shrink-0" style={{ flexGrow: d[s.key], flexBasis: 0, backgroundColor: s.color }} />
                                        ))}
                                    </span>
                                </button>
                            );
                        })}

                        {/* One tooltip for the chart, kept inside it horizontally */}
                        {active !== null && (
                            <span
                                className="pointer-events-none absolute z-10 mb-2"
                                style={{
                                    bottom: `${Math.min(100, (totals[active] / top) * 100)}%`,
                                    ...(active < n / 2 ? { left: `${(active / n) * 100}%` } : { right: `${((n - 1 - active) / n) * 100}%` }),
                                }}
                            >
                                <TooltipCard
                                    title={`${data[active].weekday} ${data[active].label}`}
                                    rows={series.map((s) => ({ label: s.label.toLowerCase(), value: formatNumber(data[active][s.key]), color: series.length > 1 ? s.color : null }))}
                                />
                            </span>
                        )}
                    </div>

                    {/* X labels positioned at their column; the edge ones align inward so none spill out */}
                    <div className="relative mt-2 h-3.5" aria-hidden="true">
                        {data.map((d, i) => {
                            if ((n - 1 - i) % every !== 0) return null;
                            const center = ((i + 0.5) / n) * 100;
                            const style = i === n - 1 ? { right: 0 } : center < 6 ? { left: 0 } : { left: `${center}%`, transform: 'translateX(-50%)' };

                            return (
                                <span key={d.date} className="absolute top-0 whitespace-nowrap text-[10px] leading-none text-brand-muted" style={style}>
                                    {d.label}
                                </span>
                            );
                        })}
                    </div>
                </div>
            </div>

            <SrTable caption={caption} columns={['Day', ...series.map((s) => s.label)]} rows={data.map((d) => [d.label, ...series.map((s) => d[s.key])])} />
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Weekday x hour heatmap (sequential, one hue)                       */
/* ------------------------------------------------------------------ */

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const hourLabel = (h) => `${String(h).padStart(2, '0')}:00`;

export const slotLabel = (d, h) => `${WEEKDAYS[d]} ${hourLabel(h)}–${hourLabel((h + 1) % 24)}`;

/** Peak-time facts pulled from a weekday x hour grid of counts - the heatmap's direct labels. */
export function PeakSummary({ grid, unit }) {
    const dayTotals = grid.map((row) => row.reduce((a, b) => a + b, 0));
    const hourTotals = Array.from({ length: 24 }, (_, h) => grid.reduce((sum, row) => sum + row[h], 0));
    const total = dayTotals.reduce((a, b) => a + b, 0);

    if (total === 0) return null;

    const busiestDay = dayTotals.indexOf(Math.max(...dayTotals));
    const busiestHour = hourTotals.indexOf(Math.max(...hourTotals));
    const quietestDay = dayTotals.indexOf(Math.min(...dayTotals));
    const weekend = Math.round(((dayTotals[5] + dayTotals[6]) / total) * 100);

    const facts = [
        ['Busiest day', WEEKDAYS[busiestDay], `${formatNumber(dayTotals[busiestDay])} ${unit}`],
        ['Busiest hour', `${hourLabel(busiestHour)}–${hourLabel((busiestHour + 1) % 24)}`, `${formatNumber(hourTotals[busiestHour])} ${unit}`],
        ['Quietest day', WEEKDAYS[quietestDay], `${formatNumber(dayTotals[quietestDay])} ${unit}`],
        ['Weekends', `${weekend}%`, 'of the total (2 of 7 days)'],
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

/**
 * Weekday x hour grid. Cells are shaded relative to the grid's own maximum,
 * so the legend states the real scale ("0 ... 57 stamps") - two views with
 * different totals can't look alike by accident. A null cell means "not
 * enough data" and is drawn as an empty outline, never as a zero.
 *
 * `describe(d, h)` words one cell for the readout / screen readers;
 * `children` renders under the legend (e.g. <PeakSummary>).
 */
export function Heatmap({ grid, ramp = MINT_RAMP, format = formatNumber, unit = '', describe, topLabel = 'Busiest', emptyText, nullNote, hours = [0, 23], children }) {
    const [active, setActive] = useState(null);
    // Only these hours are drawn (e.g. a shop's opening hours); the grid itself always has 24.
    const shown = Array.from({ length: hours[1] - hours[0] + 1 }, (_, i) => hours[0] + i);
    const everyHour = shown.length <= 14;
    const values = grid.flat().filter((v) => v !== null);
    const max = Math.max(0, ...values);
    const shade = (v) => (v === 0 || max === 0 ? ramp[0] : ramp[Math.min(5, 1 + Math.floor((v / max) * 4.999))]);
    const suffix = unit ? ` ${unit}` : '';
    const word = describe ?? ((d, h) => `${slotLabel(d, h)}: ${format(grid[d][h])}${suffix}`);

    let top = null;
    grid.forEach((row, d) =>
        row.forEach((v, h) => {
            if (v !== null && v > 0 && (!top || v > top.v)) top = { d, h, v };
        }),
    );

    return (
        <div>
            <p className="mb-3 min-h-5 truncate text-sm text-brand-muted" aria-live="polite">
                {active ? (
                    <span className="font-semibold text-brand-text">{word(active.d, active.h)}</span>
                ) : top ? (
                    <>
                        {topLabel}: <span className="font-semibold text-brand-text">{word(top.d, top.h)}</span>
                    </>
                ) : (
                    emptyText
                )}
            </p>

            <div className="overflow-x-auto pb-1">
                <div
                    className="grid gap-[2px]"
                    style={{ gridTemplateColumns: `2.25rem repeat(${shown.length}, minmax(0, 1fr))`, minWidth: `${36 + shown.length * 22}px` }}
                    onMouseLeave={() => setActive(null)}
                >
                    {grid.map((row, d) => [
                        <span key={`l${d}`} className="flex items-center text-[11px] text-brand-muted">
                            {WEEKDAYS[d]}
                        </span>,
                        ...shown.map((h) => {
                            const v = row[h];

                            return (
                                <button
                                    key={`${d}-${h}`}
                                    type="button"
                                    onMouseEnter={() => setActive({ d, h })}
                                    onFocus={() => setActive({ d, h })}
                                    onBlur={() => setActive(null)}
                                    aria-label={word(d, h)}
                                    className={`h-6 rounded-[3px] outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50 ${
                                        v === null ? 'bg-brand-card ring-1 ring-inset ring-brand-border' : ''
                                    } ${active?.d === d && active?.h === h ? 'ring-2 ring-brand-text/25' : ''}`}
                                    style={v === null ? undefined : { backgroundColor: shade(v) }}
                                />
                            );
                        }),
                    ])}
                    <span />
                    {shown.map((h) => (
                        <span key={h} className="text-center text-[10px] text-brand-muted" aria-hidden="true">
                            {everyHour || h % 3 === 0 ? String(h).padStart(2, '0') : ''}
                        </span>
                    ))}
                </div>
            </div>

            {/* Legend with the real scale, so every view states what "darkest" means */}
            <div className="mt-3 flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-[11px] text-brand-muted" aria-hidden="true">
                {nullNote && (
                    <span className="inline-flex items-center gap-1.5">
                        <span className="h-3 w-3 rounded-[3px] bg-brand-card ring-1 ring-inset ring-brand-border" />
                        {nullNote}
                    </span>
                )}
                <span className="inline-flex items-center gap-1.5">
                    <span className="tabular-nums">{format(0)}</span>
                    {ramp.map((c) => (
                        <span key={c} className="h-3 w-3 rounded-[3px]" style={{ backgroundColor: c }} />
                    ))}
                    <span className="tabular-nums">
                        {format(max)}
                        {suffix}
                    </span>
                </span>
            </div>

            {children}
        </div>
    );
}

/** Small 7D / 30D / 90D switch for a single card's own time range. */
export function RangeTabs({ ranges, value, onChange, busy = false }) {
    return (
        <div className="flex items-center gap-2">
            {busy && <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-border border-t-brand-accent" aria-label="Updating" />}
            <div className="flex rounded-lg bg-brand-bg p-0.5 ring-1 ring-brand-border" role="group" aria-label="Time range">
                {ranges.map((r) => (
                    <button
                        key={r}
                        type="button"
                        onClick={() => r !== value && onChange(r)}
                        aria-pressed={value === r}
                        aria-label={`Last ${r} days`}
                        className={`rounded-md px-2.5 py-1 text-xs font-semibold tabular-nums transition-colors ${
                            value === r ? 'bg-brand-card text-brand-text shadow-sm' : 'text-brand-muted hover:text-brand-text'
                        }`}
                    >
                        {r}D
                    </button>
                ))}
            </div>
        </div>
    );
}

/** Generic segmented switch (e.g. which metric a chart shows). */
export function Segmented({ options, value, onChange, label }) {
    return (
        <div className="flex rounded-lg bg-brand-bg p-0.5 ring-1 ring-brand-border" role="group" aria-label={label}>
            {options.map((opt) => (
                <button
                    key={opt.value}
                    type="button"
                    onClick={() => onChange(opt.value)}
                    aria-pressed={value === opt.value}
                    className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                        value === opt.value ? 'bg-brand-card text-brand-text shadow-sm' : 'text-brand-muted hover:text-brand-text'
                    }`}
                >
                    {opt.color && <span className="h-2 w-2 rounded-[2px]" style={{ backgroundColor: opt.color }} />}
                    {opt.label}
                </button>
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Horizontal bar list (funnel steps, rating split)                   */
/* ------------------------------------------------------------------ */

/** Labelled horizontal bars. `share` shows each value as a % of `of` (default: the first item). */
export function BarList({ items, of, share = true }) {
    const base = of ?? items[0]?.value ?? 0;

    return (
        <ol className="space-y-3.5">
            {items.map((item, i) => {
                const pct = base ? Math.round((item.value / base) * 100) : 0;

                return (
                    <li key={item.label}>
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="min-w-0 truncate text-brand-text">{item.label}</span>
                            <span className="shrink-0 tabular-nums">
                                <span className="font-semibold text-brand-text">{formatNumber(item.value)}</span>
                                {share && (of !== undefined || i > 0) && <span className="ml-1.5 text-xs text-brand-muted">{pct}%</span>}
                            </span>
                        </div>
                        <div className="mt-1.5 h-2 overflow-hidden rounded-full" style={{ backgroundColor: MINT_RAMP[0] }}>
                            <div className="h-full rounded-full" style={{ width: `${Math.min(100, base ? (item.value / base) * 100 : 0)}%`, backgroundColor: SERIES.primary }} />
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
