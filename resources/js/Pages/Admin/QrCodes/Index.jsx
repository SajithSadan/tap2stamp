import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import QRCode from 'qrcode';
import { useEffect, useRef, useState } from 'react';
import {
    LuDownload,
    LuExternalLink,
    LuImage,
    LuLayers,
    LuLink,
    LuLoaderCircle,
    LuPencil,
    LuPrinter,
    LuQrCode,
    LuScanLine,
    LuSearch,
    LuSparkles,
    LuUnlink,
    LuX,
} from 'react-icons/lu';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { CopyButton, EmptyState, FieldError, Pagination, Panel, StatTile, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import { downloadQrPdf, downloadQrPng } from '@/lib/qrPrint';

const PRESETS = [10, 50, 100, 250, 500, 1000];

const iconButton =
    'inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text disabled:opacity-40';

/** "/qr/K7F2QX" - the full link is long and always the same host. */
function shortLink(url) {
    try {
        return new URL(url).pathname;
    } catch {
        return url;
    }
}

/** Drops empty filters so the URL stays clean (?batch=3, not ?search=&status=&batch=3). */
function cleanFilters(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== null && v !== '' && v !== undefined));
}

function StatusBadge({ mapped }) {
    return mapped ? (
        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700">
            <LuLink className="h-3 w-3" /> Mapped
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 rounded-full bg-brand-bg px-2 py-0.5 text-xs font-medium text-brand-muted ring-1 ring-brand-border">
            <LuUnlink className="h-3 w-3" /> Unmapped
        </span>
    );
}

function GeneratePanel({ maxPerBatch }) {
    const form = useForm({ quantity: 100, name: '' });

    function submit(e) {
        e.preventDefault();
        form.post('/admin/qr-codes', { preserveScroll: true, onSuccess: () => form.reset('name') });
    }

    const quantity = Number(form.data.quantity) || 0;

    return (
        <Panel title="Generate codes" description="Each code is permanent. Map or change where it points any time.">
            <form onSubmit={submit} noValidate className="space-y-4">
                <div>
                    <label htmlFor="quantity" className="block text-sm font-medium text-brand-text">
                        How many?
                    </label>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {PRESETS.filter((n) => n <= maxPerBatch).map((n) => (
                            <button
                                key={n}
                                type="button"
                                onClick={() => form.setData('quantity', n)}
                                className={`rounded-lg px-2.5 py-1 text-xs font-semibold tabular-nums transition-colors ${
                                    quantity === n
                                        ? 'bg-brand-accent text-brand-accent-text'
                                        : 'bg-brand-bg text-brand-muted ring-1 ring-brand-border hover:text-brand-text'
                                }`}
                            >
                                {n}
                            </button>
                        ))}
                    </div>
                    <input
                        id="quantity"
                        type="number"
                        min={1}
                        max={maxPerBatch}
                        value={form.data.quantity}
                        onChange={(e) => form.setData('quantity', e.target.value === '' ? '' : Number(e.target.value))}
                        className={`${inputClass} mt-2 tabular-nums`}
                    />
                    <FieldError message={form.errors.quantity} />
                    {!form.errors.quantity && <p className="mt-1 text-xs text-brand-muted">Up to {maxPerBatch} per batch.</p>}
                </div>

                <div>
                    <label htmlFor="batch-name" className="block text-sm font-medium text-brand-text">
                        Batch name <span className="font-normal text-brand-muted">(optional)</span>
                    </label>
                    <input
                        id="batch-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g. Summer festival stickers"
                        maxLength={100}
                        className={`${inputClass} mt-1.5`}
                    />
                    <FieldError message={form.errors.name} />
                </div>

                <button type="submit" disabled={form.processing || quantity < 1} className={`${primaryButton} w-full`}>
                    {form.processing ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuSparkles className="h-4 w-4" />}
                    {form.processing ? 'Generating…' : `Generate ${quantity || ''} ${quantity === 1 ? 'code' : 'codes'}`}
                </button>

                <div className="flex items-start gap-2.5 rounded-xl bg-brand-bg px-3.5 py-3 text-xs text-brand-muted">
                    <LuScanLine className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" />
                    <p>
                        Stickers print as a clean QR, no code. To map one, stick it up, then scan it with your phone while logged in here. You'll get a
                        &ldquo;Map this sticker&rdquo; screen instead of the customer view.
                    </p>
                </div>
            </form>
        </Panel>
    );
}

function BatchesPanel({ batches, activeBatch, printing, onView, onPrint }) {
    return (
        <Panel title="Batches" description="Print a whole batch as an A4 sheet of stickers." bodyClassName="">
            {batches.length === 0 ? (
                <EmptyState icon={LuLayers} title="No batches yet">
                    Generate your first codes to create a batch.
                </EmptyState>
            ) : (
                <ul className="max-h-[26rem] divide-y divide-brand-border overflow-y-auto">
                    {batches.map((batch) => {
                        const percent = batch.codes_count ? Math.round((batch.mapped_count / batch.codes_count) * 100) : 0;
                        const busy = printing?.key === `batch-${batch.id}`;

                        return (
                            <li key={batch.id} className={`px-5 py-3.5 ${activeBatch === batch.id ? 'bg-brand-accent/5' : ''}`}>
                                <div className="flex items-start justify-between gap-3">
                                    <button type="button" onClick={() => onView(batch.id)} className="min-w-0 text-left">
                                        <p className="truncate text-sm font-semibold text-brand-text hover:underline">{batch.label}</p>
                                        <p className="text-xs text-brand-muted">
                                            {batch.created_at} · {batch.mapped_count}/{batch.codes_count} mapped
                                        </p>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => onPrint(batch)}
                                        disabled={!!printing}
                                        className={`${secondaryButton} shrink-0 px-2.5 py-1.5 text-xs`}
                                    >
                                        {busy ? <LuLoaderCircle className="h-3.5 w-3.5 animate-spin" /> : <LuPrinter className="h-3.5 w-3.5" />}
                                        {busy && printing.total ? `${printing.done}/${printing.total}` : 'PDF'}
                                    </button>
                                </div>
                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-bg">
                                    <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${percent}%` }} />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

function Modal({ title, onClose, children }) {
    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-neutral-950/50 p-0 backdrop-blur-sm sm:items-center sm:p-4" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onClick={(e) => e.stopPropagation()}
                className="max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl border border-brand-border bg-brand-card shadow-xl sm:max-w-lg sm:rounded-2xl"
            >
                <div className="flex items-center justify-between border-b border-brand-border px-5 py-4">
                    <h2 className="font-heading text-lg font-semibold text-brand-text">{title}</h2>
                    <button type="button" onClick={onClose} aria-label="Close" className={iconButton}>
                        <LuX className="h-4 w-4" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

/** One code up close: preview, its permanent link, the mapping form and downloads. */
function QrDetails({ qr, onClose, onPdf }) {
    const [preview, setPreview] = useState(null);
    const form = useForm({ destination_url: qr.destination_url ?? '' });

    useEffect(() => {
        QRCode.toDataURL(qr.scan_url, { errorCorrectionLevel: 'M', margin: 1, width: 320 })
            .then(setPreview)
            .catch(() => setPreview(null));
    }, [qr.scan_url]);

    function save(e) {
        e.preventDefault();
        form.put(`/admin/qr-codes/${qr.id}`, { preserveScroll: true, preserveState: true, onSuccess: onClose });
    }

    function unmap() {
        if (!window.confirm(`Unmap ${qr.code}? Anyone scanning it will see "Nothing found" until it's mapped again.`)) return;

        router.put(`/admin/qr-codes/${qr.id}`, { destination_url: '' }, { preserveScroll: true, preserveState: true, onSuccess: onClose });
    }

    return (
        <Modal title={`QR ${qr.code}`} onClose={onClose}>
            <div className="space-y-5 p-5">
                <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                    <div className="flex h-36 w-36 shrink-0 items-center justify-center rounded-xl border border-brand-border bg-white p-2">
                        {preview ? <img src={preview} alt={`QR code ${qr.code}`} className="h-full w-full" /> : <LuQrCode className="h-10 w-10 text-neutral-300" />}
                    </div>
                    <div className="w-full min-w-0 space-y-2">
                        <p className="font-mono text-2xl font-bold tracking-wider text-brand-text">{qr.code}</p>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge mapped={!!qr.destination_url} />
                            <span className="text-xs text-brand-muted">{qr.batch_label}</span>
                        </div>
                        <div className="flex items-center gap-1 rounded-lg bg-brand-bg py-1 pl-3 pr-1">
                            <p className="min-w-0 flex-1 select-all truncate font-mono text-xs text-brand-muted" title={qr.scan_url}>
                                {qr.scan_url}
                            </p>
                            <CopyButton text={qr.scan_url} label="Copy link" />
                            <a href={qr.scan_url} target="_blank" rel="noopener noreferrer" aria-label="Test scan link" title="Test scan link" className={iconButton}>
                                <LuExternalLink className="h-4 w-4" />
                            </a>
                        </div>
                        <p className="text-xs text-brand-muted">This link is printed in the QR and never changes.</p>
                    </div>
                </div>

                <form onSubmit={save} noValidate className="space-y-3 border-t border-brand-border pt-5">
                    <div>
                        <label htmlFor="destination_url" className="block text-sm font-medium text-brand-text">
                            Destination URL
                        </label>
                        <input
                            id="destination_url"
                            type="url"
                            inputMode="url"
                            value={form.data.destination_url}
                            onChange={(e) => form.setData('destination_url', e.target.value)}
                            placeholder="https://example.com/event/summer-festival"
                            autoFocus
                            className={`${inputClass} mt-1.5`}
                        />
                        <FieldError message={form.errors.destination_url} />
                        {!form.errors.destination_url && (
                            <p className="mt-1 text-xs text-brand-muted">Scanning the code sends people here. Leave empty to unmap.</p>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        {qr.destination_url ? (
                            <button type="button" onClick={unmap} className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">
                                <LuUnlink className="h-4 w-4" /> Unmap
                            </button>
                        ) : (
                            <span />
                        )}
                        <button type="submit" disabled={form.processing} className={primaryButton}>
                            <LuLink className="h-4 w-4" /> {form.processing ? 'Saving…' : 'Save mapping'}
                        </button>
                    </div>
                </form>

                <div className="grid grid-cols-2 gap-2 border-t border-brand-border pt-5">
                    <button type="button" onClick={() => onPdf(qr)} className={secondaryButton}>
                        <LuPrinter className="h-4 w-4" /> PDF
                    </button>
                    <button type="button" onClick={() => downloadQrPng(qr)} className={secondaryButton}>
                        <LuImage className="h-4 w-4" /> PNG
                    </button>
                </div>
            </div>
        </Modal>
    );
}

function RowActions({ qr, onEdit, onPdf }) {
    return (
        <div className="flex items-center justify-end gap-0.5">
            <button
                type="button"
                onClick={() => onEdit(qr)}
                className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-brand-accent hover:bg-brand-accent/10"
            >
                <LuPencil className="h-3.5 w-3.5" /> {qr.destination_url ? 'Edit' : 'Map'}
            </button>
            <button type="button" onClick={() => onPdf(qr)} aria-label={`Download ${qr.code} as PDF`} title="PDF" className={iconButton}>
                <LuDownload className="h-4 w-4" />
            </button>
        </div>
    );
}

export default function Index({ codes, batches, stats, filters, maxPerBatch }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState(() => new Set());
    const [editing, setEditing] = useState(null);
    const [printing, setPrinting] = useState(null);
    const [printError, setPrintError] = useState(null);
    const firstRender = useRef(true);

    function applyFilters(next) {
        router.get('/admin/qr-codes', cleanFilters({ ...filters, ...next }), { preserveState: true, preserveScroll: true, replace: true });
    }

    // Debounced search; skips the first render (the server already applied it).
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        if (search === (filters.search ?? '')) return;

        const timer = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timer);
    }, [search]);

    const pageIds = codes.data.map((qr) => qr.id);
    const allOnPageSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const hasFilters = !!(filters.search || filters.batch || filters.status);

    function toggle(id) {
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);

            return next;
        });
    }

    function togglePage() {
        setSelected((prev) => {
            const next = new Set(prev);
            pageIds.forEach((id) => (allOnPageSelected ? next.delete(id) : next.add(id)));

            return next;
        });
    }

    async function makePdf(key, load) {
        setPrintError(null);
        setPrinting({ key, done: 0, total: 0 });

        try {
            const { title, codes: list } = await load();
            await downloadQrPdf({ title, codes: list, onProgress: (done, total) => setPrinting({ key, done, total }) });
        } catch {
            setPrintError("Couldn't build the PDF. Please try again.");
        } finally {
            setPrinting(null);
        }
    }

    const printBatch = (batch) =>
        makePdf(`batch-${batch.id}`, async () => (await axios.post('/admin/qr-codes/print', { batch: batch.id })).data);

    const printSelected = () =>
        makePdf('selected', async () => (await axios.post('/admin/qr-codes/print', { ids: [...selected] })).data);

    // A single code is already on the page - no round trip needed.
    const printOne = (qr) => makePdf(`code-${qr.id}`, async () => ({ title: `QR ${qr.code}`, codes: [qr] }));

    const activeBatch = batches.find((b) => b.id === filters.batch);

    return (
        <AdminLayout title="QR codes" description="Generate printable QR stickers in bulk, then point each one wherever you like.">
            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatTile icon={LuQrCode} label="Total codes" value={stats.total} />
                <StatTile icon={LuLink} label="Mapped" value={stats.mapped} hint="Redirecting to a URL" />
                <StatTile icon={LuUnlink} label="Unmapped" value={stats.unmapped} hint='Show "Nothing found"' />
                <StatTile icon={LuLayers} label="Batches" value={stats.batches} />
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <GeneratePanel maxPerBatch={maxPerBatch} />
                <BatchesPanel batches={batches} activeBatch={filters.batch} printing={printing} onView={(id) => applyFilters({ batch: id, page: null })} onPrint={printBatch} />
            </div>

            {printError && (
                <p role="alert" className="mt-4 rounded-xl bg-red-50 px-4 py-2.5 text-sm text-red-700">
                    {printError}
                </p>
            )}

            <Panel className="mt-6" bodyClassName="" title="All codes" description={`${codes.total} ${codes.total === 1 ? 'code' : 'codes'}${hasFilters ? ' match' : ''}`}>
                {/* Toolbar */}
                <div className="flex flex-col gap-2 border-b border-brand-border px-5 py-3 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <LuSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search code or destination URL"
                            aria-label="Search code or destination URL"
                            className={`${inputClass} pl-9`}
                        />
                    </div>
                    <select
                        value={filters.batch ?? ''}
                        onChange={(e) => applyFilters({ batch: e.target.value ? Number(e.target.value) : null, page: null })}
                        aria-label="Filter by batch"
                        className={`${inputClass} md:w-56`}
                    >
                        <option value="">All batches</option>
                        {batches.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.label}
                            </option>
                        ))}
                    </select>
                    <div className="flex rounded-xl bg-brand-bg p-1 ring-1 ring-brand-border" role="group" aria-label="Filter by status">
                        {[
                            [null, 'All'],
                            ['mapped', 'Mapped'],
                            ['unmapped', 'Unmapped'],
                        ].map(([value, label]) => (
                            <button
                                key={label}
                                type="button"
                                onClick={() => applyFilters({ status: value, page: null })}
                                aria-pressed={filters.status === value}
                                className={`flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                    filters.status === value ? 'bg-brand-card text-brand-text shadow-sm' : 'text-brand-muted hover:text-brand-text'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Active batch / selection bar */}
                {(activeBatch || selected.size > 0) && (
                    <div className="flex flex-wrap items-center gap-2 border-b border-brand-border bg-brand-accent/5 px-5 py-2.5 text-sm">
                        {selected.size > 0 ? (
                            <>
                                <span className="font-semibold text-brand-text">{selected.size} selected</span>
                                <button type="button" onClick={() => setSelected(new Set())} className="text-xs font-medium text-brand-muted hover:text-brand-text">
                                    Clear
                                </button>
                                <button type="button" onClick={printSelected} disabled={!!printing} className={`${primaryButton} ml-auto px-3 py-1.5 text-xs`}>
                                    {printing?.key === 'selected' ? <LuLoaderCircle className="h-3.5 w-3.5 animate-spin" /> : <LuPrinter className="h-3.5 w-3.5" />}
                                    {printing?.key === 'selected' && printing.total ? `Building ${printing.done}/${printing.total}…` : 'Print selected (PDF)'}
                                </button>
                            </>
                        ) : (
                            <>
                                <span className="text-brand-muted">
                                    Showing <strong className="text-brand-text">{activeBatch.label}</strong>
                                </span>
                                <button
                                    type="button"
                                    onClick={() => printBatch(activeBatch)}
                                    disabled={!!printing}
                                    className={`${primaryButton} ml-auto px-3 py-1.5 text-xs`}
                                >
                                    <LuPrinter className="h-3.5 w-3.5" /> Print this batch
                                </button>
                            </>
                        )}
                    </div>
                )}

                {codes.data.length === 0 ? (
                    hasFilters ? (
                        <EmptyState icon={LuSearch} title="No codes match these filters">
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    router.get('/admin/qr-codes', {}, { preserveScroll: true });
                                }}
                                className={`${secondaryButton} mt-2`}
                            >
                                Clear filters
                            </button>
                        </EmptyState>
                    ) : (
                        <EmptyState icon={LuQrCode} title="No QR codes yet">
                            Generate your first batch above. Each code gets a permanent scan link you can map later.
                        </EmptyState>
                    )
                ) : (
                    <>
                        {/* Desktop table */}
                        <table className="hidden w-full text-left md:table">
                            <thead>
                                <tr className="border-b border-brand-border text-xs uppercase tracking-wide text-brand-muted">
                                    <th className="w-10 py-3 pl-5">
                                        <input type="checkbox" checked={allOnPageSelected} onChange={togglePage} aria-label="Select all on this page" className="h-4 w-4" />
                                    </th>
                                    <th className="px-3 py-3 font-medium">Code</th>
                                    <th className="px-3 py-3 font-medium">Batch</th>
                                    <th className="px-3 py-3 font-medium">Destination</th>
                                    <th className="px-3 py-3 font-medium">Status</th>
                                    <th className="px-3 py-3 font-medium">Created</th>
                                    <th className="py-3 pr-4" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-border">
                                {codes.data.map((qr) => (
                                    <tr key={qr.id} className={`transition-colors hover:bg-brand-bg/60 ${selected.has(qr.id) ? 'bg-brand-accent/5' : ''}`}>
                                        <td className="py-3 pl-5">
                                            <input type="checkbox" checked={selected.has(qr.id)} onChange={() => toggle(qr.id)} aria-label={`Select ${qr.code}`} className="h-4 w-4" />
                                        </td>
                                        <td className="px-3 py-3">
                                            <button type="button" onClick={() => setEditing(qr)} className="text-left">
                                                <p className="font-mono text-sm font-bold tracking-wide text-brand-text hover:underline">{qr.code}</p>
                                                <p className="font-mono text-[11px] text-brand-muted">{shortLink(qr.scan_url)}</p>
                                            </button>
                                        </td>
                                        <td className="max-w-40 px-3 py-3">
                                            <p className="truncate text-xs text-brand-muted" title={qr.batch_label}>
                                                {qr.batch_label}
                                            </p>
                                        </td>
                                        <td className="max-w-72 px-3 py-3">
                                            {qr.destination_url ? (
                                                <a
                                                    href={qr.destination_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title={qr.destination_url}
                                                    className="block truncate text-sm text-brand-text hover:text-brand-accent hover:underline"
                                                >
                                                    {qr.destination_url}
                                                </a>
                                            ) : (
                                                <span className="text-sm text-brand-muted">—</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusBadge mapped={!!qr.destination_url} />
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-xs text-brand-muted">{qr.created_at}</td>
                                        <td className="py-3 pr-4">
                                            <RowActions qr={qr} onEdit={setEditing} onPdf={printOne} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* Mobile cards */}
                        <div className="flex items-center gap-2 border-b border-brand-border px-4 py-2.5 md:hidden">
                            <input id="select-page" type="checkbox" checked={allOnPageSelected} onChange={togglePage} className="h-4 w-4" />
                            <label htmlFor="select-page" className="text-xs font-medium text-brand-muted">
                                Select all on this page
                            </label>
                        </div>
                        <ul className="divide-y divide-brand-border md:hidden">
                            {codes.data.map((qr) => (
                                <li key={qr.id} className={`flex gap-3 px-4 py-3.5 ${selected.has(qr.id) ? 'bg-brand-accent/5' : ''}`}>
                                    <input type="checkbox" checked={selected.has(qr.id)} onChange={() => toggle(qr.id)} aria-label={`Select ${qr.code}`} className="mt-1 h-4 w-4 shrink-0" />
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="font-mono text-sm font-bold tracking-wide text-brand-text">{qr.code}</p>
                                            <StatusBadge mapped={!!qr.destination_url} />
                                        </div>
                                        <p className="truncate text-sm text-brand-muted">{qr.destination_url ?? 'Not mapped yet'}</p>
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-xs text-brand-muted">
                                                {qr.batch_label} · {qr.created_at}
                                            </p>
                                            <RowActions qr={qr} onEdit={setEditing} onPdf={printOne} />
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination paginator={codes} />
            </Panel>

            {editing && <QrDetails qr={editing} onClose={() => setEditing(null)} onPdf={printOne} />}
        </AdminLayout>
    );
}
