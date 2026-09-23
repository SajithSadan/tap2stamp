import { useState } from 'react';

/** Rounds up to a tidy axis top that splits evenly into two gridline steps. */
function niceMax(value) {
    if (value <= 4) return 4;

    const step = 10 ** Math.floor(Math.log10(value));
    const top = Math.ceil(value / (step / 2)) * (step / 2);

    return top % 2 === 0 ? top : top + step / 2;
}

/**
 * Single-series column chart of stamps per day. One series, so no legend -
 * the panel title names it. Hover/focus a column for the day's numbers; a
 * visually hidden table carries the same data for screen readers.
 */
export default function DailyBarChart({ days }) {
    const [active, setActive] = useState(null);
    const top = niceMax(Math.max(0, ...days.map((d) => d.stamps)));
    const ticks = [top, top / 2, 0];

    return (
        <div>
            <div className="flex gap-2">
                {/* Y axis labels */}
                <div className="flex h-48 flex-col justify-between py-0 text-right text-[11px] tabular-nums text-brand-muted" aria-hidden="true">
                    {ticks.map((t) => (
                        <span key={t} className="-translate-y-1/2 leading-none first:translate-y-0 last:translate-y-0">
                            {t}
                        </span>
                    ))}
                </div>

                <div className="relative flex-1">
                    {/* Recessive gridlines */}
                    <div className="pointer-events-none absolute inset-0 flex h-48 flex-col justify-between" aria-hidden="true">
                        {ticks.map((t) => (
                            <div key={t} className={`border-t ${t === 0 ? 'border-brand-border' : 'border-dashed border-brand-border/70'}`} />
                        ))}
                    </div>

                    <div className="relative flex h-48 items-end gap-[2px]" onMouseLeave={() => setActive(null)}>
                        {days.map((day, i) => {
                            const pct = (day.stamps / top) * 100;
                            const dimmed = active !== null && active !== i;

                            return (
                                <button
                                    key={day.date}
                                    type="button"
                                    onMouseEnter={() => setActive(i)}
                                    onFocus={() => setActive(i)}
                                    onBlur={() => setActive(null)}
                                    aria-label={`${day.label}: ${day.stamps} stamps, ${day.redeemed} rewards redeemed`}
                                    className="group relative flex h-full flex-1 items-end justify-center outline-none"
                                >
                                    <span
                                        className={`block w-full max-w-7 rounded-t-[4px] bg-brand-accent transition-opacity group-focus-visible:ring-2 group-focus-visible:ring-brand-accent/40 ${
                                            dimmed ? 'opacity-40' : ''
                                        }`}
                                        style={{ height: `${pct}%` }}
                                    />

                                    {active === i && (
                                        <span
                                            className={`pointer-events-none absolute bottom-full z-10 mb-2 w-max rounded-lg border border-brand-border bg-brand-card px-3 py-2 text-left shadow-lg ${
                                                i < 3 ? 'left-0' : i > days.length - 4 ? 'right-0' : 'left-1/2 -translate-x-1/2'
                                            }`}
                                            style={{ bottom: `${Math.max(pct, 0)}%` }}
                                        >
                                            <span className="block text-xs font-medium text-brand-muted">
                                                {day.weekday} {day.label}
                                            </span>
                                            <span className="mt-0.5 block text-sm font-semibold text-brand-text">
                                                {day.stamps} {day.stamps === 1 ? 'stamp' : 'stamps'}
                                            </span>
                                            <span className="block text-xs text-brand-muted">
                                                {day.redeemed} {day.redeemed === 1 ? 'reward' : 'rewards'} redeemed
                                            </span>
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {/* X axis: every other day, so labels never collide on a phone. */}
                    <div className="mt-2 flex gap-[2px]" aria-hidden="true">
                        {days.map((day, i) => (
                            <span key={day.date} className="flex-1 text-center text-[10px] text-brand-muted">
                                {(days.length - 1 - i) % 2 === 0 ? day.label : ''}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            <table className="sr-only">
                <caption>Stamps and rewards redeemed per day</caption>
                <thead>
                    <tr>
                        <th scope="col">Day</th>
                        <th scope="col">Stamps</th>
                        <th scope="col">Rewards redeemed</th>
                    </tr>
                </thead>
                <tbody>
                    {days.map((day) => (
                        <tr key={day.date}>
                            <th scope="row">{day.label}</th>
                            <td>{day.stamps}</td>
                            <td>{day.redeemed}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
