import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { LuCheck, LuCopy, LuDownload, LuLoaderCircle, LuRefreshCw, LuSearch, LuTrash2 } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { inputClass, secondaryButton, Switch } from '@/Components/Dashboard/Ui';

const LIVE_EVERY_MS = 5000;

/** Monolog's levels, grouped into the four the page filters by. */
const LEVELS = {
    error: { label: 'Errors', chip: 'bg-red-500/10 text-red-700 ring-red-500/30', line: 'border-red-500 bg-red-500/10 text-red-200' },
    warning: { label: 'Warnings', chip: 'bg-amber-500/10 text-amber-700 ring-amber-500/30', line: 'border-amber-400 bg-amber-400/5 text-amber-100' },
    info: { label: 'Info', chip: 'bg-sky-500/10 text-sky-700 ring-sky-500/30', line: 'border-sky-500/70 text-slate-200' },
    debug: { label: 'Debug', chip: 'bg-slate-500/10 text-slate-600 ring-slate-500/30', line: 'border-slate-600 text-slate-400' },
};

const GROUP = { EMERGENCY: 'error', ALERT: 'error', CRITICAL: 'error', ERROR: 'error', WARNING: 'warning', NOTICE: 'info', INFO: 'info', DEBUG: 'debug' };

// "[2026-10-06 14:50:01] local.ERROR: message" starts an entry; anything else
// (stack trace, wrapped JSON) belongs to the entry above it.
const ENTRY = /^\[[^\]]+\]\s+[\w-]+\.([A-Z]+):/;

function parse(lines) {
    let level = 'debug';
    return lines.map((text, i) => {
        const match = text.match(ENTRY);
        if (match) level = GROUP[match[1]] ?? 'info';
        return { n: i + 1, text, level, entry: Boolean(match) };
    });
}

const formatSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

export default function Logs({ lineOptions }) {
    const [count, setCount] = useState(200);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [live, setLive] = useState(false);
    const [query, setQuery] = useState('');
    const [levels, setLevels] = useState([]);
    const [fetchedAt, setFetchedAt] = useState(null);
    const [copied, setCopied] = useState(false);
    const [confirm, confirmDialog] = useConfirm();
    const scroller = useRef(null);

    const load = useCallback(
        async (quiet = false) => {
            if (!quiet) setLoading(true);
            try {
                const { data: result } = await axios.get('/admin/logs/lines', { params: { lines: count } });
                // Stay pinned to the newest line, unless the reader has scrolled up.
                const box = scroller.current;
                const atBottom = !box || box.scrollHeight - box.scrollTop - box.clientHeight < 40;
                setData(result);
                setFailed(false);
                setFetchedAt(new Date());
                if (atBottom) requestAnimationFrame(() => box && (box.scrollTop = box.scrollHeight));
            } catch {
                setFailed(true);
            } finally {
                setLoading(false);
            }
        },
        [count],
    );

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        if (!live) return;
        const timer = setInterval(() => load(true), LIVE_EVERY_MS);
        return () => clearInterval(timer);
    }, [live, load]);

    const parsed = useMemo(() => parse(data?.lines ?? []), [data]);
    const counts = useMemo(() => parsed.reduce((sum, l) => (l.entry ? { ...sum, [l.level]: (sum[l.level] ?? 0) + 1 } : sum), {}), [parsed]);
    const needle = query.trim().toLowerCase();
    const shown = parsed.filter((l) => (levels.length === 0 || levels.includes(l.level)) && (!needle || l.text.toLowerCase().includes(needle)));

    async function copy() {
        try {
            await navigator.clipboard.writeText(shown.map((l) => l.text).join('\n'));
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard blocked (http) - nothing to do
        }
    }

    async function clear() {
        const ok = await confirm({
            title: 'Clear the log?',
            message: 'Everything in storage/logs/laravel.log is deleted. Download it first if you might need it.',
            confirmLabel: 'Clear log',
            danger: true,
        });
        if (!ok) return;
        await axios.post('/admin/logs/clear');
        load();
    }

    return (
        <AdminLayout
            title="Logs"
            description="The end of storage/logs/laravel.log, newest at the bottom."
            actions={
                <div className="flex flex-wrap gap-2">
                    <a href="/admin/logs/download" className={secondaryButton}>
                        <LuDownload className="h-4 w-4" /> Download
                    </a>
                    <button type="button" onClick={clear} className={`${secondaryButton} hover:!text-red-600`}>
                        <LuTrash2 className="h-4 w-4" /> Clear
                    </button>
                </div>
            }
        >
            {confirmDialog}

            <div className="mb-3 flex flex-wrap items-center gap-2">
                {Object.entries(LEVELS).map(([key, level]) => {
                    const active = levels.includes(key);
                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setLevels(active ? levels.filter((l) => l !== key) : [...levels, key])}
                            aria-pressed={active}
                            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset transition ${
                                active ? level.chip : 'bg-brand-card text-brand-muted ring-brand-border hover:text-brand-text'
                            }`}
                        >
                            {level.label} <span className="tabular-nums">{counts[key] ?? 0}</span>
                        </button>
                    );
                })}

                <div className="relative min-w-48 flex-1 sm:max-w-xs">
                    <LuSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                    <input type="text" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filter lines" aria-label="Filter lines" className={`${inputClass} !pl-9`} />
                </div>

                <div className="ml-auto flex flex-wrap items-center gap-2">
                    <select value={count} onChange={(e) => setCount(Number(e.target.value))} aria-label="Lines to show" className={`${inputClass} !w-auto`}>
                        {lineOptions.map((n) => (
                            <option key={n} value={n}>
                                Last {n} lines
                            </option>
                        ))}
                    </select>
                    <Switch checked={live} onChange={setLive} label="Live" />
                    <button type="button" onClick={() => load()} disabled={loading} className={secondaryButton} aria-label="Refresh">
                        <LuRefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                    </button>
                    <button type="button" onClick={copy} disabled={shown.length === 0} className={secondaryButton} aria-label="Copy shown lines">
                        {copied ? <LuCheck className="h-4 w-4 text-brand-accent" /> : <LuCopy className="h-4 w-4" />}
                    </button>
                </div>
            </div>

            {/* Always dark: log text is light, so the panel colour is set inline, not left to a class. */}
            <div className="overflow-hidden rounded-2xl border border-slate-800" style={{ backgroundColor: '#0B0F14' }}>
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 px-4 py-2 text-xs text-slate-400">
                    <span>
                        {data?.exists ? `${shown.length} of ${parsed.length} lines · ${formatSize(data.size)}` : data ? 'No log file yet' : 'Loading…'}
                        {live && <span className="ml-2 inline-flex items-center gap-1 text-emerald-400"><span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400" /> Live</span>}
                    </span>
                    {fetchedAt && <span>Updated {fetchedAt.toLocaleTimeString('en-GB')}</span>}
                </div>

                <div ref={scroller} className="max-h-[70vh] overflow-auto font-mono text-xs leading-5" style={{ backgroundColor: '#0B0F14' }}>
                    {failed ? (
                        <p className="px-4 py-10 text-center text-red-300">Couldn't read the log. Try Refresh.</p>
                    ) : !data && loading ? (
                        <p className="flex items-center justify-center gap-2 px-4 py-10 text-slate-400">
                            <LuLoaderCircle className="h-4 w-4 animate-spin" /> Reading the log…
                        </p>
                    ) : shown.length === 0 ? (
                        <p className="px-4 py-10 text-center text-slate-400">{parsed.length ? 'No lines match.' : 'The log is empty.'}</p>
                    ) : (
                        <table className="w-full border-collapse">
                            <tbody>
                                {shown.map((line) => (
                                    <tr key={line.n} className={`border-l-2 ${LEVELS[line.level].line}`}>
                                        <td className="select-none px-3 text-right align-top tabular-nums text-slate-600">{line.n}</td>
                                        <td className={`whitespace-pre-wrap break-all pr-4 ${line.entry ? '' : 'pl-4 opacity-60'}`}>{line.text || ' '}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
