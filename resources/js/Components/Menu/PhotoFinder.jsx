import { useEffect, useState } from "react";
import { LuCheck, LuImage, LuImageOff, LuLoaderCircle, LuScanEye, LuSearch, LuSearchX, LuSparkles, LuSquare, LuX } from "react-icons/lu";

/**
 * Progress panel for "Find photos" (MenuEditor). The server does search →
 * download → AI check → save in one request per item, so while it runs the
 * steps advance on a clock; the outcome (and which step it stopped at) is
 * the server's real answer for that item.
 */

const STEPS = [
    { todo: "Search the catalog", doing: "Searching the catalog", done: "Found in the catalog" },
    { todo: "Download the image", doing: "Downloading the image", done: "Image downloaded" },
    { todo: "AI check", doing: "AI is checking the photo", done: "AI approved it" },
    { todo: "Save to the menu", doing: "Saving", done: "Saved to the menu" },
];

/** Rough split of a request's time, only used until the answer arrives. */
const STEP_MS = [1400, 1400];

/** Index of the step an outcome stopped at (STEPS.length = all done). */
function stopStep(outcome) {
    if (outcome.status === "found") return STEPS.length;
    if (outcome.status === "rejected") return 2;
    return outcome.reason === "Not in the product catalog" ? 0 : 1;
}

function failText(outcome) {
    if (outcome.status === "rejected") return "AI said it's not a match";
    return outcome.reason === "Not in the product catalog" ? "Not in the catalog" : "No usable image";
}

function useElapsed(startedAt, running) {
    const [now, setNow] = useState(Date.now());
    useEffect(() => {
        if (!running) return;
        setNow(Date.now());
        const id = setInterval(() => setNow(Date.now()), 200);
        return () => clearInterval(id);
    }, [startedAt, running]);
    return now - startedAt;
}

/* ---------- The tile: what's happening right now ---------- */

function Stage({ step, outcome, name }) {
    const frame = "relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl";

    if (outcome?.status === "found") {
        return (
            <div className={`${frame} motion-safe:animate-pf-pop`}>
                <img src={outcome.image_url} alt={name} className="h-full w-full object-cover" />
                <span className="absolute bottom-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text ring-2 ring-white">
                    <LuCheck className="h-3 w-3" strokeWidth={3} />
                </span>
            </div>
        );
    }

    if (outcome) {
        const rejected = outcome.status === "rejected";
        const Icon = rejected ? LuImageOff : LuSearchX;
        return (
            <div className={`${frame} ${rejected ? "bg-amber-50" : "bg-brand-bg"} motion-safe:animate-pf-shake`}>
                <Icon className={`h-7 w-7 ${rejected ? "text-amber-500" : "text-brand-muted"}`} />
            </div>
        );
    }

    if (step === 0) {
        return (
            <div className={`${frame} bg-brand-accent/10`}>
                <LuSearch className="h-7 w-7 text-brand-accent motion-safe:animate-pf-orbit" />
            </div>
        );
    }

    if (step === 1) {
        return (
            <div className={`${frame} bg-brand-accent/10`}>
                <LuImage className="h-7 w-7 text-brand-accent/40" />
                <span className="absolute inset-x-0 bottom-0 h-1 bg-brand-accent/20">
                    <span className="block h-full w-1/3 bg-brand-accent motion-safe:animate-pf-slide" />
                </span>
            </div>
        );
    }

    // AI check: a glowing band sweeping over the frame.
    return (
        <div className={`${frame} bg-brand-deep`}>
            <LuScanEye className="h-7 w-7 text-white/85" />
            <span className="absolute inset-x-0 top-0 h-8 bg-gradient-to-b from-transparent via-brand-accent/40 to-transparent motion-safe:animate-pf-scan">
                <span className="absolute inset-x-0 top-1/2 h-px bg-brand-accent" />
            </span>
        </div>
    );
}

/* ---------- Checklist beside the tile ---------- */

function Checklist({ step, outcome }) {
    const stop = outcome ? stopStep(outcome) : null;

    return (
        <ul className="mt-2.5 space-y-1.5 text-sm">
            {STEPS.map((s, i) => {
                const done = outcome ? i < stop : i < step;
                const failed = outcome && i === stop;
                const active = !outcome && i === step;
                const skipped = outcome && i > stop;
                const rejected = outcome?.status === "rejected";

                return (
                    <li key={s.todo} className={`flex items-center gap-2 transition-opacity duration-300 ${skipped ? "opacity-35" : ""}`}>
                        <span
                            className={`flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full ${
                                done
                                    ? "bg-brand-accent text-brand-accent-text"
                                    : failed
                                      ? rejected
                                          ? "bg-amber-500 text-white"
                                          : "bg-brand-muted text-white"
                                      : active
                                        ? "text-brand-accent"
                                        : "border border-brand-border"
                            }`}
                        >
                            {done && <LuCheck className="h-2.5 w-2.5 motion-safe:animate-pf-pop" strokeWidth={4} />}
                            {failed && <LuX className="h-2.5 w-2.5" strokeWidth={4} />}
                            {active && <LuLoaderCircle className="h-[18px] w-[18px] animate-spin" />}
                        </span>
                        <span
                            className={
                                failed
                                    ? `font-medium ${rejected ? "text-amber-600" : "text-brand-text"}`
                                    : active
                                      ? "font-medium text-brand-text"
                                      : done
                                        ? i === STEPS.length - 1
                                            ? "font-medium text-brand-accent"
                                            : "text-brand-muted"
                                        : "text-brand-muted/70"
                            }
                        >
                            {failed ? failText(outcome) : done ? s.done : active ? `${s.doing}…` : s.todo}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

/* ---------- One segment per item, coloured by its outcome ---------- */

const SEGMENT = { found: "bg-brand-accent", rejected: "bg-amber-400", not_found: "bg-brand-muted/40" };

function Segments({ total, results, index, finished }) {
    return (
        <div className="mt-3 flex gap-1" aria-hidden="true">
            {Array.from({ length: total }, (_, i) => {
                const result = results[i];
                const current = !finished && !result && i === index;
                return (
                    <span
                        key={i}
                        className={`h-1.5 min-w-[3px] flex-1 rounded-full transition-colors duration-500 ${
                            result ? (SEGMENT[result.status] ?? SEGMENT.not_found) : current ? "bg-brand-accent/40 motion-safe:animate-pulse" : "bg-brand-bg"
                        }`}
                    />
                );
            })}
        </div>
    );
}

/** What Gemini saw and why it said no ("Closest: a bag of beans - Not a drink" → "a bag of beans - Not a drink"). */
function aiReason(reason) {
    return (reason ?? "").replace(/^Closest:\s*/, "");
}

/* ---------- When it's all done ---------- */

function ResultRow({ result }) {
    const thumb = "flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg";
    const rejected = result.status === "rejected";

    return (
        <li className="flex items-center gap-3 px-3 py-2 motion-safe:animate-pf-in">
            {result.status === "found" ? (
                <img src={result.image_url} alt="" className={`${thumb} object-cover`} />
            ) : (
                <span className={`${thumb} ${rejected ? "bg-amber-50" : "bg-brand-bg"}`}>
                    {rejected ? <LuImageOff className="h-4 w-4 text-amber-500" /> : <LuSearchX className="h-4 w-4 text-brand-muted" />}
                </span>
            )}
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-brand-text">{result.name}</p>
                {result.status === "found" ? (
                    <p className="text-xs font-medium text-brand-accent">Photo added</p>
                ) : rejected ? (
                    <p className="line-clamp-2 text-xs text-brand-muted">
                        <span className="font-medium text-amber-600">AI said no:</span> {aiReason(result.reason) || "not a match"}
                    </p>
                ) : (
                    <p className="text-xs text-brand-muted">{failText(result)}</p>
                )}
            </div>
        </li>
    );
}

function Summary({ results }) {
    const count = (status) => results.filter((r) => r.status === status).length;
    const found = count("found");
    const rejected = count("rejected");
    const missing = results.length - found - rejected;
    const stat = (value, label, dot) => (
        <div className="flex-1 rounded-xl bg-brand-bg px-3 py-2">
            <p className="text-lg font-semibold tabular-nums text-brand-text">{value}</p>
            <p className="flex items-center gap-1.5 text-xs text-brand-muted">
                <span className={`h-2 w-2 rounded-full ${dot}`} /> {label}
            </p>
        </div>
    );

    return (
        <div className="mt-4 flex gap-2">
            {stat(found, found === 1 ? "Photo added" : "Photos added", SEGMENT.found)}
            {stat(missing, "Not in catalog", SEGMENT.not_found)}
            {stat(rejected, "No match", SEGMENT.rejected)}
        </div>
    );
}

/**
 * @param {{run: {total: number, index: number, current: ?{name: string, section: string, startedAt: number},
 *   outcome: ?{status: string, image_url: ?string, reason: ?string}, results: object[], finished: boolean, note: ?string},
 *   onClose: () => void}} props
 */
export default function PhotoFinder({ run, onStop, onClose }) {
    const { total, index, current, outcome, results, finished, note } = run;
    const elapsed = useElapsed(current?.startedAt ?? 0, Boolean(current) && !outcome);
    const step = elapsed < STEP_MS[0] ? 0 : elapsed < STEP_MS[0] + STEP_MS[1] ? 1 : 2;

    return (
        <section className="rounded-2xl border border-brand-border bg-brand-card p-4" role="status" aria-live="polite">
            <div className="flex items-center gap-2">
                <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-accent/10">
                    {finished ? <LuCheck className="h-4 w-4 text-brand-accent" strokeWidth={3} /> : <LuSparkles className="h-4 w-4 text-brand-accent" />}
                </span>
                <h3 className="mr-auto text-sm font-semibold text-brand-text">
                    {!finished ? "Finding photos" : total === 0 ? "Nothing left to find" : "Photos done"}
                </h3>
                {total > 0 && (
                    <span className="text-xs tabular-nums text-brand-muted">
                        {finished ? results.length : Math.min(index + 1, total)} of {total}
                    </span>
                )}
                {!finished && (
                    <button
                        type="button"
                        onClick={onStop}
                        disabled={run.stopping}
                        title="Stops after the item being checked now"
                        className="flex items-center gap-1.5 rounded-lg border border-brand-border px-2 py-1 text-xs font-medium text-brand-text transition-colors hover:border-red-300 hover:text-red-600 disabled:cursor-default disabled:hover:border-brand-border disabled:hover:text-brand-muted disabled:text-brand-muted"
                    >
                        {run.stopping ? <LuLoaderCircle className="h-3 w-3 animate-spin" /> : <LuSquare className="h-2.5 w-2.5 fill-current" />}
                        {run.stopping ? "Stopping…" : "Stop"}
                    </button>
                )}
                {finished && (
                    <button type="button" onClick={onClose} aria-label="Close" className="-mr-1 rounded-md p-1 text-brand-muted hover:bg-brand-bg hover:text-brand-text">
                        <LuX className="h-4 w-4" />
                    </button>
                )}
            </div>

            {total > 1 && <Segments total={total} results={results} index={index} finished={finished} />}

            {!finished && current && (
                <div key={current.startedAt} className="mt-4 flex items-start gap-4 motion-safe:animate-pf-in">
                    <Stage step={step} outcome={outcome} name={current.name} />
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold leading-tight text-brand-text">{current.name}</p>
                        {current.section && <p className="mt-0.5 truncate text-[11px] uppercase tracking-wide text-brand-muted">{current.section}</p>}
                        <Checklist step={step} outcome={outcome} />
                        {outcome?.status === "rejected" && (
                            <p className="mt-1.5 line-clamp-2 pl-[26px] text-xs text-brand-muted">{aiReason(outcome.reason)}</p>
                        )}
                    </div>
                </div>
            )}

            {finished && results.length > 1 && <Summary results={results} />}

            {note && <p className="mt-3 text-xs text-brand-muted">{note}</p>}

            {finished && results.length > 0 && (
                <ul className="mt-3 max-h-72 divide-y divide-brand-border overflow-y-auto rounded-xl border border-brand-border [scrollbar-width:thin]">
                    {results.map((r, i) => (
                        <ResultRow key={i} result={r} />
                    ))}
                </ul>
            )}
        </section>
    );
}
