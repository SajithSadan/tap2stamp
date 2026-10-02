import { Link, router, useForm } from "@inertiajs/react";
import axios from "axios";
import QRCode from "qrcode";
import { useEffect, useRef, useState } from "react";
import {
    LuExternalLink,
    LuImage,
    LuLayers,
    LuLink,
    LuLoaderCircle,
    LuPalette,
    LuPencil,
    LuPrinter,
    LuQrCode,
    LuScanLine,
    LuSearch,
    LuSparkles,
    LuTrash2,
    LuUnlink,
    LuX,
} from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import QrDestinationField, {
    ShopPicker,
} from "@/Components/Dashboard/QrDestinationField";
import {
    CopyButton,
    EmptyState,
    FieldError,
    Pagination,
    Panel,
    StatTile,
    inputClass,
    primaryButton,
    secondaryButton,
} from "@/Components/Dashboard/Ui";
import QrDesignStage from "@/Components/QrDesignStage";
import QrStickerScanner from "@/Components/QrStickerScanner";
import {
    canBeBackOf,
    designLayout,
    downloadQrPng,
    openPrintWindow,
    printQrPdf,
    stickerSize,
} from "@/lib/qrPrint";
import { useQrPreview } from "@/lib/qrRender";
import { blockAspect } from "@/lib/qrStyle";

const PRESETS = [10, 50, 100, 250, 500, 1000];

// The last print choices (design, layout), remembered per browser - a convenience only.
const DESIGN_KEY = "qr_print_design";
const LAYOUT_KEY = "qr_print_layout";
const SIDES_KEY = "qr_print_sides";
const BACK_KEY = "qr_print_back";

function remembered(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function remember(key, value) {
    try {
        value
            ? localStorage.setItem(key, String(value))
            : localStorage.removeItem(key);
    } catch {
        // Storage blocked (private mode etc.) - the choice just isn't remembered.
    }
}

/** Plain QRs on a sheet: the 4 x 5 grid in qrPrint.js. */
const PLAIN_PER_SHEET = 20;

const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

/** A saved design's thumbnail with its QR in place, for the print dialog. */
function DesignThumb({ design }) {
    const qrSrc = useQrPreview(design.style, design.logo_url, 160);
    return (
        <QrDesignStage
            imageUrl={design.image_url}
            aspect={design.image_height / design.image_width}
            block={blockAspect(design.style)}
            qr={{ x: design.qr_x, y: design.qr_y, size: design.qr_size }}
            qrSrc={qrSrc}
            serialStyle={design.style}
            className="rounded"
        />
    );
}

function ChoiceCard({ active, onClick, children }) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            onClick={onClick}
            className={`flex flex-col overflow-hidden rounded-xl text-left transition ${
                active
                    ? "ring-2 ring-brand-accent"
                    : "ring-1 ring-brand-border hover:ring-brand-accent/60"
            }`}
        >
            {children}
        </button>
    );
}

/**
 * Asked on every Print: which design (or plain QR) and which layout, with
 * the page count for each. Opens the print view on confirm - inside the
 * click, so the new tab isn't blocked as a popup.
 */
function PrintDialog({ job, designs, initial, onClose, onPrint }) {
    const [designId, setDesignId] = useState(
        designs.some((d) => d.id === initial.designId)
            ? initial.designId
            : null,
    );
    const [onePerPage, setOnePerPage] = useState(initial.onePerPage);
    const [doubleSided, setDoubleSided] = useState(initial.doubleSided);
    // null = the back is the same as the front.
    const [backId, setBackId] = useState(initial.backId);
    const design = designs.find((d) => d.id === designId) ?? null;
    // Backs must be the same printed size as the front, so they line up.
    const backChoices = design
        ? designs.filter((d) => d.id !== design.id && canBeBackOf(design, d))
        : [];
    const backDesign = backChoices.find((d) => d.id === backId) ?? null;

    const sides = doubleSided ? 2 : 1;
    const perSheet = design ? designLayout(design).perPage : PLAIN_PER_SHEET;
    const size = design ? stickerSize(design) : null;
    const pagesFor = (perPage) => Math.ceil(job.count / perPage) * sides;
    const layouts = [
        {
            value: false,
            label: "Sheet (A4)",
            detail: `${perSheet} per page · ${plural(pagesFor(perSheet), "page")}`,
            hint: "Several on each A4 page, with cut lines.",
        },
        {
            value: true,
            label: "One per page",
            detail: `${plural(pagesFor(1), "page")} · ${size ? `${Math.round(size.w)} × ${Math.round(size.h)} mm each` : "A4"}`,
            hint: size
                ? "Each page is exactly the sticker’s size."
                : "One large QR per A4 page.",
        },
    ];
    const totalPages = pagesFor(onePerPage ? 1 : perSheet);

    return (
        <Modal title={`Print ${job.title}`} onClose={onClose} wide>
            <div className="space-y-6 p-5">
                <div>
                    <h3 className="text-sm font-semibold text-brand-text">
                        Design
                    </h3>
                    <div
                        role="radiogroup"
                        aria-label="Design"
                        className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <ChoiceCard
                            active={designId === null}
                            onClick={() => setDesignId(null)}
                        >
                            <div className="flex aspect-[4/3] items-center justify-center bg-brand-bg">
                                <LuQrCode className="h-10 w-10 text-brand-text" />
                            </div>
                            <div className="border-t border-brand-border px-3 py-2">
                                <p className="text-sm font-semibold text-brand-text">
                                    Plain QR
                                </p>
                                <p className="text-xs text-brand-muted">
                                    No artwork · no new design association
                                </p>
                            </div>
                        </ChoiceCard>
                        {designs.map((d) => {
                            const s = stickerSize(d);
                            return (
                                <ChoiceCard
                                    key={d.id}
                                    active={designId === d.id}
                                    onClick={() => setDesignId(d.id)}
                                >
                                    <div className="flex aspect-[4/3] items-center justify-center bg-brand-bg p-2">
                                        <div
                                            className="h-full"
                                            style={{
                                                aspectRatio: `${d.image_width} / ${d.image_height}`,
                                                maxWidth: "100%",
                                            }}
                                        >
                                            <DesignThumb design={d} />
                                        </div>
                                    </div>
                                    <div className="border-t border-brand-border px-3 py-2">
                                        <p className="truncate text-sm font-semibold text-brand-text">
                                            {d.name}
                                        </p>
                                        <p className="text-xs tabular-nums text-brand-muted">
                                            {d.preset && (
                                                <span className="capitalize">
                                                    {d.preset} ·{" "}
                                                </span>
                                            )}
                                            {Math.round(s.w / 10)} ×{" "}
                                            {Math.round(s.h / 10)} cm ·{" "}
                                            {d.codes_count} codes tracked
                                        </p>
                                    </div>
                                </ChoiceCard>
                            );
                        })}
                    </div>
                    {designs.length === 0 && (
                        <p className="mt-2 text-xs text-brand-muted">
                            Want your artwork around the QR?{" "}
                            <Link
                                href="/admin/qr-codes/designs/create"
                                className="font-semibold text-brand-accent hover:underline"
                            >
                                Create a sticker design
                            </Link>
                            .
                        </p>
                    )}
                </div>

                <div>
                    <h3 className="text-sm font-semibold text-brand-text">
                        Layout
                    </h3>
                    <div
                        role="radiogroup"
                        aria-label="Layout"
                        className="mt-2 grid gap-3 sm:grid-cols-2"
                    >
                        {layouts.map((l) => (
                            <ChoiceCard
                                key={l.label}
                                active={onePerPage === l.value}
                                onClick={() => setOnePerPage(l.value)}
                            >
                                <div className="px-4 py-3">
                                    <p className="text-sm font-semibold text-brand-text">
                                        {l.label}
                                    </p>
                                    <p className="mt-0.5 text-xs tabular-nums text-brand-text">
                                        {l.detail}
                                    </p>
                                    <p className="mt-1 text-xs text-brand-muted">
                                        {l.hint}
                                    </p>
                                </div>
                            </ChoiceCard>
                        ))}
                    </div>
                </div>

                <div>
                    <h3 className="text-sm font-semibold text-brand-text">
                        Sides
                    </h3>
                    <div
                        role="radiogroup"
                        aria-label="Sides"
                        className="mt-2 grid gap-3 sm:grid-cols-2"
                    >
                        {[
                            [false, "Single-sided", "Front only."],
                            [
                                true,
                                "Double-sided",
                                "A back side for every sticker, printed right after its front.",
                            ],
                        ].map(([value, label, hint]) => (
                            <ChoiceCard
                                key={label}
                                active={doubleSided === value}
                                onClick={() => setDoubleSided(value)}
                            >
                                <div className="px-4 py-3">
                                    <p className="text-sm font-semibold text-brand-text">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-xs text-brand-muted">
                                        {hint}
                                    </p>
                                </div>
                            </ChoiceCard>
                        ))}
                    </div>

                    {doubleSided && (
                        <div className="mt-3">
                            <p className="text-xs font-semibold text-brand-text">
                                Back side
                            </p>
                            <div
                                role="radiogroup"
                                aria-label="Back side"
                                className="mt-1.5 flex flex-wrap gap-2"
                            >
                                {[
                                    { id: null, name: "Same as front" },
                                    ...backChoices,
                                ].map((d) => {
                                    const active =
                                        (backDesign?.id ?? null) === d.id;
                                    return (
                                        <button
                                            key={d.id ?? "same"}
                                            type="button"
                                            role="radio"
                                            aria-checked={active}
                                            onClick={() => setBackId(d.id)}
                                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                                active
                                                    ? "bg-brand-accent/10 text-brand-text ring-2 ring-brand-accent"
                                                    : "text-brand-muted ring-1 ring-brand-border hover:text-brand-text"
                                            }`}
                                        >
                                            {d.name}
                                        </button>
                                    );
                                })}
                            </div>
                            <p className="mt-1.5 text-xs text-brand-muted">
                                Each back carries the same code’s QR as its
                                front.
                                {design &&
                                    backChoices.length === 0 &&
                                    " Other designs need the same print size to be used as a back."}
                            </p>
                        </div>
                    )}
                </div>

                <p className="flex items-start gap-2 text-xs text-brand-muted">
                    <LuPrinter className="mt-px h-3.5 w-3.5 shrink-0" />
                    <span>
                        The print dialog opens in a new tab. Print at 100%
                        (Actual size), not “Fit to page”, so sizes come out
                        exact.
                        {doubleSided && (
                            <strong className="font-semibold text-brand-text">
                                {" "}
                                Turn on two-sided printing, flip on long edge.
                            </strong>
                        )}
                    </span>
                </p>

                <div className="flex justify-end gap-2 border-t border-brand-border pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className={secondaryButton}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            onPrint({
                                design,
                                onePerPage,
                                doubleSided,
                                backDesign: doubleSided ? backDesign : null,
                            })
                        }
                        className={primaryButton}
                    >
                        <LuPrinter className="h-4 w-4" /> Print{" "}
                        {plural(job.count, "code")} ·{" "}
                        {plural(totalPages, "page")}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

const iconButton =
    "inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text disabled:opacity-40";

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
    return Object.fromEntries(
        Object.entries(filters).filter(
            ([, v]) => v !== null && v !== "" && v !== undefined,
        ),
    );
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
    const form = useForm({ quantity: 100, name: "" });

    function submit(e) {
        e.preventDefault();
        form.post("/admin/qr-codes", {
            preserveScroll: true,
            onSuccess: () => form.reset("name"),
        });
    }

    const quantity = Number(form.data.quantity) || 0;

    return (
        <Panel
            title="Generate codes"
            description="Each code is permanent. Map or change where it points any time."
        >
            <form onSubmit={submit} noValidate className="space-y-4">
                <div>
                    <label
                        htmlFor="quantity"
                        className="block text-sm font-medium text-brand-text"
                    >
                        How many?
                    </label>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {PRESETS.filter((n) => n <= maxPerBatch).map((n) => (
                            <button
                                key={n}
                                type="button"
                                onClick={() => form.setData("quantity", n)}
                                className={`rounded-lg px-2.5 py-1 text-xs font-semibold tabular-nums transition-colors ${
                                    quantity === n
                                        ? "bg-brand-accent text-brand-accent-text"
                                        : "bg-brand-bg text-brand-muted ring-1 ring-brand-border hover:text-brand-text"
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
                        onChange={(e) =>
                            form.setData(
                                "quantity",
                                e.target.value === ""
                                    ? ""
                                    : Number(e.target.value),
                            )
                        }
                        className={`${inputClass} mt-2 tabular-nums`}
                    />
                    <FieldError message={form.errors.quantity} />
                    {!form.errors.quantity && (
                        <p className="mt-1 text-xs text-brand-muted">
                            Up to {maxPerBatch} per batch.
                        </p>
                    )}
                </div>

                <div>
                    <label
                        htmlFor="batch-name"
                        className="block text-sm font-medium text-brand-text"
                    >
                        Batch name{" "}
                        <span className="font-normal text-brand-muted">
                            (optional)
                        </span>
                    </label>
                    <input
                        id="batch-name"
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        placeholder="e.g. Summer festival stickers"
                        maxLength={100}
                        className={`${inputClass} mt-1.5`}
                    />
                    <FieldError message={form.errors.name} />
                </div>

                <button
                    type="submit"
                    disabled={form.processing || quantity < 1}
                    className={`${primaryButton} w-full`}
                >
                    {form.processing ? (
                        <LuLoaderCircle className="h-4 w-4 animate-spin" />
                    ) : (
                        <LuSparkles className="h-4 w-4" />
                    )}
                    {form.processing
                        ? "Generating…"
                        : `Generate ${quantity || ""} ${quantity === 1 ? "code" : "codes"}`}
                </button>

                <div className="flex items-start gap-2.5 rounded-xl bg-brand-bg px-3.5 py-3 text-xs text-brand-muted">
                    <LuScanLine className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" />
                    <p>
                        Stickers print as a clean QR, no code. To map one, stick
                        it up, then scan it with your phone while logged in
                        here. You'll get a &ldquo;Map this sticker&rdquo; screen
                        instead of the customer view.
                    </p>
                </div>
            </form>
        </Panel>
    );
}

function BatchesPanel({
    batches,
    activeBatch,
    printing,
    onView,
    onPrint,
    onDelete,
    lastDesign,
    lastOnePerPage,
    lastDoubleSided,
}) {
    return (
        <Panel
            title="Batches"
            description="Click Print… to choose the design and layout."
            bodyClassName=""
            action={
                // The choices the print dialog will start with (the last ones used).
                <p className="flex items-center gap-1.5 rounded-lg bg-brand-bg px-2.5 py-1.5 text-xs text-brand-muted ring-1 ring-brand-border">
                    <LuPalette className="h-3.5 w-3.5 shrink-0 text-brand-accent" />
                    Prints with{" "}
                    <strong className="font-semibold text-brand-text">
                        {lastDesign?.name ?? "Plain QR"}
                    </strong>{" "}
                    · {lastOnePerPage ? "One per page" : "Sheet (A4)"}
                    {lastDoubleSided && " · Double-sided"}
                </p>
            }
        >
            {batches.length === 0 ? (
                <EmptyState icon={LuLayers} title="No batches yet">
                    Generate your first codes to create a batch.
                </EmptyState>
            ) : (
                <ul className="max-h-[26rem] divide-y divide-brand-border overflow-y-auto">
                    {batches.map((batch) => {
                        const percent = batch.codes_count
                            ? Math.round(
                                  (batch.mapped_count / batch.codes_count) *
                                      100,
                              )
                            : 0;
                        const busy = printing?.key === `batch-${batch.id}`;

                        return (
                            <li
                                key={batch.id}
                                className={`px-5 py-3.5 ${activeBatch === batch.id ? "bg-brand-accent/5" : ""}`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <button
                                        type="button"
                                        onClick={() => onView(batch.id)}
                                        className="min-w-0 text-left"
                                    >
                                        <p className="truncate text-sm font-semibold text-brand-text hover:underline">
                                            {batch.label}
                                        </p>
                                        <p className="text-xs text-brand-muted">
                                            {batch.created_at} ·{" "}
                                            {batch.mapped_count}/
                                            {batch.codes_count} mapped
                                        </p>
                                    </button>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => onDelete(batch)}
                                            disabled={!!printing}
                                            aria-label={`Delete ${batch.label}`}
                                            title="Delete this batch and its codes"
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-40"
                                        >
                                            <LuTrash2 className="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => onPrint(batch)}
                                            disabled={!!printing}
                                            className={`${secondaryButton} shrink-0 px-2.5 py-1.5 text-xs`}
                                        >
                                            {busy ? (
                                                <LuLoaderCircle className="h-3.5 w-3.5 animate-spin" />
                                            ) : (
                                                <LuPrinter className="h-3.5 w-3.5" />
                                            )}
                                            {busy && printing.total
                                                ? `${printing.done}/${printing.total}`
                                                : "Print…"}
                                        </button>
                                    </div>
                                </div>
                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-brand-bg">
                                    <div
                                        className="h-full rounded-full bg-emerald-500 transition-all"
                                        style={{ width: `${percent}%` }}
                                    />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

function Modal({ title, onClose, wide = false, children }) {
    useEffect(() => {
        const onKey = (e) => e.key === "Escape" && onClose();
        window.addEventListener("keydown", onKey);

        return () => window.removeEventListener("keydown", onKey);
    }, [onClose]);

    return (
        <div
            className="fixed inset-0 z-40 flex items-end justify-center bg-neutral-950/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            onClick={onClose}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onClick={(e) => e.stopPropagation()}
                className={`max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl border border-brand-border bg-brand-card shadow-xl sm:rounded-2xl ${wide ? "sm:max-w-2xl" : "sm:max-w-lg"}`}
            >
                <div className="flex items-center justify-between border-b border-brand-border px-5 py-4">
                    <h2 className="font-heading text-lg font-semibold text-brand-text">
                        {title}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close"
                        className={iconButton}
                    >
                        <LuX className="h-4 w-4" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

/** One code up close: preview, its permanent link, the mapping form and downloads. */
function QrDetails({ qr, shops, design, onClose, onPdf, onDelete, confirm }) {
    const [preview, setPreview] = useState(null);
    const form = useForm({ destination_url: qr.destination_url ?? "" });

    useEffect(() => {
        QRCode.toDataURL(qr.scan_url, {
            errorCorrectionLevel: "M",
            margin: 1,
            width: 320,
        })
            .then(setPreview)
            .catch(() => setPreview(null));
    }, [qr.scan_url]);

    function save(e) {
        e.preventDefault();
        form.put(`/admin/qr-codes/${qr.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onClose,
        });
    }

    async function unmap() {
        const ok = await confirm({
            title: `Unmap ${qr.code}?`,
            message:
                "Anyone scanning it will see “Nothing found” until it’s mapped again.",
            confirmLabel: "Unmap",
            danger: true,
        });
        if (!ok) return;

        router.put(
            `/admin/qr-codes/${qr.id}`,
            { destination_url: "" },
            { preserveScroll: true, preserveState: true, onSuccess: onClose },
        );
    }

    return (
        <Modal title={`QR ${qr.code}`} onClose={onClose}>
            <div className="space-y-5 p-5">
                <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                    <div className="flex h-36 w-36 shrink-0 items-center justify-center rounded-xl border border-brand-border bg-white p-2">
                        {preview ? (
                            <img
                                src={preview}
                                alt={`QR code ${qr.code}`}
                                className="h-full w-full"
                            />
                        ) : (
                            <LuQrCode className="h-10 w-10 text-neutral-300" />
                        )}
                    </div>
                    <div className="w-full min-w-0 space-y-2">
                        <p className="font-mono text-2xl font-bold tracking-wider text-brand-text">
                            {qr.code}
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge mapped={!!qr.destination_url} />
                            <span className="text-xs text-brand-muted">
                                {qr.batch_label}
                            </span>
                            {qr.shop_name && (
                                <span className="rounded-full bg-brand-accent/10 px-2 py-0.5 text-xs font-medium text-brand-text">
                                    {qr.shop_name}
                                </span>
                            )}
                        </div>
                        {qr.design_names?.length > 0 && (
                            <p className="text-xs text-brand-muted">
                                Printed with:{" "}
                                <span className="font-medium text-brand-text">
                                    {qr.design_names.join(", ")}
                                </span>
                            </p>
                        )}
                        <div className="flex items-center gap-1 rounded-lg bg-brand-bg py-1 pl-3 pr-1">
                            <p
                                className="min-w-0 flex-1 select-all truncate font-mono text-xs text-brand-muted"
                                title={qr.scan_url}
                            >
                                {qr.scan_url}
                            </p>
                            <CopyButton text={qr.scan_url} label="Copy link" />
                            <a
                                href={qr.scan_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Test scan link"
                                title="Test scan link"
                                className={iconButton}
                            >
                                <LuExternalLink className="h-4 w-4" />
                            </a>
                        </div>
                        <p className="text-xs text-brand-muted">
                            This link is printed in the QR and never changes.
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={save}
                    noValidate
                    className="space-y-3 border-t border-brand-border pt-5"
                >
                    <QrDestinationField
                        value={form.data.destination_url}
                        onChange={(url) => form.setData("destination_url", url)}
                        shops={shops}
                        error={form.errors.destination_url}
                        autoFocus
                    />
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        {qr.destination_url ? (
                            <button
                                type="button"
                                onClick={unmap}
                                className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                <LuUnlink className="h-4 w-4" /> Unmap
                            </button>
                        ) : (
                            <span />
                        )}
                        <button
                            type="submit"
                            disabled={form.processing}
                            className={primaryButton}
                        >
                            <LuLink className="h-4 w-4" />{" "}
                            {form.processing ? "Saving…" : "Save mapping"}
                        </button>
                    </div>
                </form>

                <div className="grid grid-cols-2 gap-2 border-t border-brand-border pt-5">
                    <button
                        type="button"
                        onClick={() => onPdf(qr)}
                        className={secondaryButton}
                    >
                        <LuPrinter className="h-4 w-4" /> Print…
                    </button>
                    <button
                        type="button"
                        onClick={() => downloadQrPng(qr, design)}
                        className={secondaryButton}
                    >
                        <LuImage className="h-4 w-4" /> PNG
                    </button>
                </div>
                <p className="-mt-3 text-center text-xs text-brand-muted">
                    {design ? `On the “${design.name}” design.` : "Plain QR."}
                </p>
                <div className="border-t border-brand-border pt-4 text-center">
                    <button
                        type="button"
                        onClick={() => onDelete([qr.id])}
                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                    >
                        <LuTrash2 className="h-4 w-4" /> Delete this code
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
                <LuPencil className="h-3.5 w-3.5" />{" "}
                {qr.destination_url ? "Edit" : "Map"}
            </button>
            <button
                type="button"
                onClick={() => onPdf(qr)}
                aria-label={`Print ${qr.code}`}
                title="Print"
                className={iconButton}
            >
                <LuPrinter className="h-4 w-4" />
            </button>
        </div>
    );
}

export default function Index({
    codes,
    batches,
    shops,
    stats,
    filters,
    maxPerBatch,
    designs,
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    // The last print choices, pre-selected in the print dialog next time.
    const [designId, setDesignId] = useState(
        () => Number(remembered(DESIGN_KEY)) || null,
    );
    const [onePerPage, setOnePerPage] = useState(
        () => remembered(LAYOUT_KEY) === "page",
    );
    const [doubleSided, setDoubleSided] = useState(
        () => remembered(SIDES_KEY) === "double",
    );
    const [backId, setBackId] = useState(
        () => Number(remembered(BACK_KEY)) || null,
    );
    // A remembered design that has since been deleted falls back to plain.
    const design = designs.find((d) => d.id === designId) ?? null;
    // What the print dialog is about to print: { key, title, count, load }.
    const [printJob, setPrintJob] = useState(null);
    const [confirm, confirmDialog] = useConfirm();
    const [selected, setSelected] = useState(() => new Set());
    const [editing, setEditing] = useState(null);
    // The in-app camera scanner: scan a sticker to open its "Map this sticker" screen.
    const [scanning, setScanning] = useState(false);
    const [printing, setPrinting] = useState(null);
    const [printError, setPrintError] = useState(null);
    const firstRender = useRef(true);

    function applyFilters(next) {
        router.get("/admin/qr-codes", cleanFilters({ ...filters, ...next }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    // Debounced search; skips the first render (the server already applied it).
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        if (search === (filters.search ?? "")) return;

        const timer = setTimeout(() => applyFilters({ search }), 300);

        return () => clearTimeout(timer);
    }, [search]);

    const pageIds = codes.data.map((qr) => qr.id);
    const allOnPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const hasFilters = !!(
        filters.search ||
        filters.batch ||
        filters.status ||
        filters.shop ||
        filters.design
    );

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
            pageIds.forEach((id) =>
                allOnPageSelected ? next.delete(id) : next.add(id),
            );

            return next;
        });
    }

    // Opens the print view: the tab is opened right in the click (so it isn't
    // blocked as a popup), then filled with the PDF, which brings up the print dialog.
    async function makePdf({ key, load }, options) {
        setPrintError(null);
        const win = openPrintWindow();
        setPrinting({ key, done: 0, total: 0 });

        try {
            const { title, codes: list } = await load(options);
            await printQrPdf(
                {
                    title,
                    codes: list,
                    ...options,
                    onProgress: (done, total) =>
                        setPrinting({ key, done, total }),
                },
                win,
            );
            const designIds = [options.design?.id];
            if (options.doubleSided)
                designIds.push(options.backDesign?.id ?? options.design?.id);
            try {
                await axios.post("/admin/qr-codes/record-print", {
                    ids: list.map((code) => code.id),
                    design_ids: [...new Set(designIds.filter(Boolean))],
                });
                router.reload({
                    only: ["codes", "designs"],
                    preserveScroll: true,
                });
            } catch {
                setPrintError(
                    "The print PDF is ready, but the designs used could not be recorded. Refresh and try again.",
                );
            }
        } catch {
            win?.close();
            setPrintError("Couldn't build the print. Please try again.");
        } finally {
            setPrinting(null);
        }
    }

    /** The print dialog's "Print": remember the choices, close the dialog, open the print view. */
    function confirmPrint(options) {
        const job = printJob;
        setPrintJob(null);
        setDesignId(options.design?.id ?? null);
        setOnePerPage(options.onePerPage);
        setDoubleSided(options.doubleSided);
        setBackId(options.backDesign?.id ?? null);
        remember(DESIGN_KEY, options.design?.id);
        remember(LAYOUT_KEY, options.onePerPage ? "page" : null);
        remember(SIDES_KEY, options.doubleSided ? "double" : null);
        remember(BACK_KEY, options.backDesign?.id);
        makePdf(job, options);
    }

    // Each Print button opens the dialog first; the codes are only fetched once you confirm.
    const printBatch = (batch) =>
        setPrintJob({
            key: `batch-${batch.id}`,
            title: batch.label,
            count: batch.codes_count,
            load: async () =>
                (await axios.post("/admin/qr-codes/print", { batch: batch.id }))
                    .data,
        });

    const printSelected = () =>
        setPrintJob({
            key: "selected",
            title: `${selected.size} selected`,
            count: selected.size,
            load: async () =>
                (
                    await axios.post("/admin/qr-codes/print", {
                        ids: [...selected],
                    })
                ).data,
        });

    /**
     * Deletes a batch and all its codes. Printed stickers from it stop working,
     * so it always asks - and if any code is mapped (probably in use), you
     * have to type DELETE.
     */
    async function deleteBatch(batch) {
        const mapped = batch.mapped_count > 0;
        const ok = await confirm({
            title: `Delete ${batch.label}?`,
            message:
                `This deletes the batch and its ${plural(batch.codes_count, "code")}. Any stickers already printed from it will stop working (they’ll show “Nothing found”). This can’t be undone.` +
                (mapped
                    ? `\n\n${batch.mapped_count} of these codes are mapped, so they’re probably in use.`
                    : ""),
            confirmLabel: "Delete batch",
            danger: true,
            requireText: mapped ? "DELETE" : null,
        });
        if (!ok) return;

        router.delete(`/admin/qr-codes/batches/${batch.id}`, {
            preserveScroll: true,
            // Selected codes may have been in that batch.
            onSuccess: () => setSelected(new Set()),
        });
    }

    /**
     * Deletes single codes (the details dialog, or the selection). Printed
     * stickers with them stop working, so it always asks - and you type
     * DELETE when any is mapped (or selected on another page, so we can't tell).
     */
    async function deleteCodes(ids) {
        const onPage = codes.data.filter((qr) => ids.includes(qr.id));
        const mapped = onPage.filter((qr) => qr.destination_url).length;
        const unseen = ids.length - onPage.length;
        const what =
            ids.length === 1 && onPage.length === 1
                ? onPage[0].code
                : plural(ids.length, "code");

        const ok = await confirm({
            title: `Delete ${what}?`,
            message:
                `Any printed stickers with ${ids.length === 1 ? "it" : "them"} will stop working (they’ll show “Nothing found”). The rest of the batch keeps its serial numbers. This can’t be undone.` +
                (mapped > 0
                    ? `\n\n${mapped === ids.length && ids.length === 1 ? "It's" : `${mapped} of them ${mapped === 1 ? "is" : "are"}`} mapped, so probably in use.`
                    : "") +
                (unseen > 0
                    ? `\n\n${unseen} selected on other pages ${unseen === 1 ? "isn't" : "aren't"} shown here.`
                    : ""),
            confirmLabel: ids.length === 1 ? "Delete code" : `Delete ${plural(ids.length, "code")}`,
            danger: true,
            requireText: mapped > 0 || unseen > 0 ? "DELETE" : null,
        });
        if (!ok) return;

        router.delete("/admin/qr-codes", {
            data: { ids },
            preserveScroll: true,
            onSuccess: () => {
                setEditing(null);
                setSelected((prev) => {
                    const next = new Set(prev);
                    ids.forEach((id) => next.delete(id));

                    return next;
                });
            },
        });
    }

    // A single code is already on the page - no round trip needed.
    const printOne = (qr) => {
        setEditing(null);
        setPrintJob({
            key: `code-${qr.id}`,
            title: `QR ${qr.code}`,
            count: 1,
            load: async () =>
                (await axios.post("/admin/qr-codes/print", { ids: [qr.id] }))
                    .data,
        });
    };

    const activeBatch = batches.find((b) => b.id === filters.batch);

    return (
        <AdminLayout
            title="QR codes"
            description="Generate printable QR stickers in bulk, then point each one wherever you like."
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link
                        href="/admin/qr-codes/designs"
                        className={secondaryButton}
                    >
                        <LuPalette className="h-4 w-4" /> Sticker designs
                    </Link>
                    <button
                        type="button"
                        onClick={() => setScanning(true)}
                        className={primaryButton}
                    >
                        <LuScanLine className="h-4 w-4" /> Scan sticker
                    </button>
                </div>
            }
        >
            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatTile
                    compact
                    icon={LuQrCode}
                    label="Total codes"
                    value={stats.total}
                />
                <StatTile
                    compact
                    icon={LuLink}
                    label="Mapped"
                    value={stats.mapped}
                    hint="Redirecting to a URL"
                />
                <StatTile
                    compact
                    icon={LuUnlink}
                    label="Unmapped"
                    value={stats.unmapped}
                    hint='Show "Nothing found"'
                />
                <StatTile
                    compact
                    icon={LuLayers}
                    label="Batches"
                    value={stats.batches}
                />
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <GeneratePanel maxPerBatch={maxPerBatch} />
                <BatchesPanel
                    batches={batches}
                    activeBatch={filters.batch}
                    printing={printing}
                    onView={(id) => applyFilters({ batch: id, page: null })}
                    onPrint={printBatch}
                    onDelete={deleteBatch}
                    lastDesign={design}
                    lastOnePerPage={onePerPage}
                    lastDoubleSided={doubleSided}
                />
            </div>

            {printError && (
                <p
                    role="alert"
                    className="mt-4 rounded-xl bg-red-50 px-4 py-2.5 text-sm text-red-700"
                >
                    {printError}
                </p>
            )}

            <Panel
                className="mt-6"
                bodyClassName=""
                title="All codes"
                description={`${codes.total} ${codes.total === 1 ? "code" : "codes"}${hasFilters ? " match" : ""}`}
            >
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
                        value={filters.batch ?? ""}
                        onChange={(e) =>
                            applyFilters({
                                batch: e.target.value
                                    ? Number(e.target.value)
                                    : null,
                                page: null,
                            })
                        }
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
                    <div className="md:w-56">
                        <ShopPicker
                            shops={shops}
                            value={filters.shop ?? ""}
                            onChange={(shopId) =>
                                applyFilters({
                                    shop: shopId ? Number(shopId) : null,
                                    page: null,
                                })
                            }
                            label="Filter by assigned shop"
                            emptyLabel="All shops"
                        />
                    </div>
                    <select
                        value={filters.design ?? ""}
                        onChange={(e) =>
                            applyFilters({
                                design: e.target.value
                                    ? Number(e.target.value)
                                    : null,
                                page: null,
                            })
                        }
                        aria-label="Filter by sticker design"
                        className={`${inputClass} md:w-56`}
                    >
                        <option value="">All designs</option>
                        {designs.map((stickerDesign) => (
                            <option
                                key={stickerDesign.id}
                                value={stickerDesign.id}
                            >
                                {stickerDesign.name} (
                                {stickerDesign.codes_count})
                            </option>
                        ))}
                    </select>
                    <div
                        className="flex rounded-xl bg-brand-bg p-1 ring-1 ring-brand-border"
                        role="group"
                        aria-label="Filter by status"
                    >
                        {[
                            [null, "All"],
                            ["mapped", "Mapped"],
                            ["unmapped", "Unmapped"],
                        ].map(([value, label]) => (
                            <button
                                key={label}
                                type="button"
                                onClick={() =>
                                    applyFilters({ status: value, page: null })
                                }
                                aria-pressed={filters.status === value}
                                className={`flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                    filters.status === value
                                        ? "bg-brand-card text-brand-text shadow-sm"
                                        : "text-brand-muted hover:text-brand-text"
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    {hasFilters && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch("");
                                applyFilters({
                                    search: "",
                                    batch: null,
                                    shop: null,
                                    design: null,
                                    status: null,
                                    page: null,
                                });
                            }}
                            className={`${secondaryButton} shrink-0`}
                        >
                            <LuX className="h-4 w-4" /> Clear filters
                        </button>
                    )}
                </div>

                {/* Active batch / selection bar */}
                {(activeBatch || selected.size > 0) && (
                    <div className="flex flex-wrap items-center gap-2 border-b border-brand-border bg-brand-accent/5 px-5 py-2.5 text-sm">
                        {selected.size > 0 ? (
                            <>
                                <span className="font-semibold text-brand-text">
                                    {selected.size} selected
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setSelected(new Set())}
                                    className="text-xs font-medium text-brand-muted hover:text-brand-text"
                                >
                                    Clear
                                </button>
                                <button
                                    type="button"
                                    onClick={() => deleteCodes([...selected])}
                                    className="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                >
                                    <LuTrash2 className="h-3.5 w-3.5" /> Delete
                                    selected
                                </button>
                                <button
                                    type="button"
                                    onClick={printSelected}
                                    disabled={!!printing}
                                    className={`${primaryButton} px-3 py-1.5 text-xs`}
                                >
                                    {printing?.key === "selected" ? (
                                        <LuLoaderCircle className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <LuPrinter className="h-3.5 w-3.5" />
                                    )}
                                    {printing?.key === "selected" &&
                                    printing.total
                                        ? `Building ${printing.done}/${printing.total}…`
                                        : "Print selected…"}
                                </button>
                            </>
                        ) : (
                            <>
                                <span className="text-brand-muted">
                                    Showing{" "}
                                    <strong className="text-brand-text">
                                        {activeBatch.label}
                                    </strong>
                                </span>
                                <button
                                    type="button"
                                    onClick={() => printBatch(activeBatch)}
                                    disabled={!!printing}
                                    className={`${primaryButton} ml-auto px-3 py-1.5 text-xs`}
                                >
                                    <LuPrinter className="h-3.5 w-3.5" /> Print
                                    this batch…
                                </button>
                            </>
                        )}
                    </div>
                )}

                {codes.data.length === 0 ? (
                    hasFilters ? (
                        <EmptyState
                            icon={LuSearch}
                            title="No codes match these filters"
                        >
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch("");
                                    router.get(
                                        "/admin/qr-codes",
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                                className={`${secondaryButton} mt-2`}
                            >
                                Clear filters
                            </button>
                        </EmptyState>
                    ) : (
                        <EmptyState icon={LuQrCode} title="No QR codes yet">
                            Generate your first batch above. Each code gets a
                            permanent scan link you can map later.
                        </EmptyState>
                    )
                ) : (
                    <>
                        {/* Desktop table */}
                        <table className="hidden w-full text-left md:table">
                            <thead>
                                <tr className="border-b border-brand-border text-xs uppercase tracking-wide text-brand-muted">
                                    <th className="w-10 py-3 pl-5">
                                        <input
                                            type="checkbox"
                                            checked={allOnPageSelected}
                                            onChange={togglePage}
                                            aria-label="Select all on this page"
                                            className="h-4 w-4"
                                        />
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Code
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Batch
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Destination
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Assigned shop
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Sticker design
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Created
                                    </th>
                                    <th className="py-3 pr-4" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-border">
                                {codes.data.map((qr) => (
                                    <tr
                                        key={qr.id}
                                        className={`transition-colors hover:bg-brand-bg/60 ${selected.has(qr.id) ? "bg-brand-accent/5" : ""}`}
                                    >
                                        <td className="py-3 pl-5">
                                            <input
                                                type="checkbox"
                                                checked={selected.has(qr.id)}
                                                onChange={() => toggle(qr.id)}
                                                aria-label={`Select ${qr.code}`}
                                                className="h-4 w-4"
                                            />
                                        </td>
                                        <td className="px-3 py-3">
                                            <button
                                                type="button"
                                                onClick={() => setEditing(qr)}
                                                className="text-left"
                                            >
                                                <p className="font-mono text-sm font-bold tracking-wide text-brand-text hover:underline">
                                                    {qr.code}
                                                </p>
                                                <p className="font-mono text-[11px] text-brand-muted">
                                                    {shortLink(qr.scan_url)}
                                                </p>
                                            </button>
                                        </td>
                                        <td className="max-w-40 px-3 py-3">
                                            <p
                                                className="truncate text-xs text-brand-muted"
                                                title={qr.batch_label}
                                            >
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
                                                <span className="text-sm text-brand-muted">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="max-w-48 px-3 py-3">
                                            <p
                                                className="truncate text-sm text-brand-text"
                                                title={qr.shop_name ?? ""}
                                            >
                                                {qr.shop_name ?? "—"}
                                            </p>
                                        </td>
                                        <td className="max-w-48 px-3 py-3">
                                            <p
                                                className="truncate text-sm text-brand-text"
                                                title={
                                                    qr.design_names?.join(
                                                        ", ",
                                                    ) ?? ""
                                                }
                                            >
                                                {qr.design_names?.join(", ") ||
                                                    "—"}
                                            </p>
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusBadge
                                                mapped={!!qr.destination_url}
                                            />
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-3 text-xs text-brand-muted">
                                            {qr.created_at}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <RowActions
                                                qr={qr}
                                                onEdit={setEditing}
                                                onPdf={printOne}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* Mobile cards */}
                        <div className="flex items-center gap-2 border-b border-brand-border px-4 py-2.5 md:hidden">
                            <input
                                id="select-page"
                                type="checkbox"
                                checked={allOnPageSelected}
                                onChange={togglePage}
                                className="h-4 w-4"
                            />
                            <label
                                htmlFor="select-page"
                                className="text-xs font-medium text-brand-muted"
                            >
                                Select all on this page
                            </label>
                        </div>
                        <ul className="divide-y divide-brand-border md:hidden">
                            {codes.data.map((qr) => (
                                <li
                                    key={qr.id}
                                    className={`flex gap-3 px-4 py-3.5 ${selected.has(qr.id) ? "bg-brand-accent/5" : ""}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selected.has(qr.id)}
                                        onChange={() => toggle(qr.id)}
                                        aria-label={`Select ${qr.code}`}
                                        className="mt-1 h-4 w-4 shrink-0"
                                    />
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="font-mono text-sm font-bold tracking-wide text-brand-text">
                                                {qr.code}
                                            </p>
                                            <StatusBadge
                                                mapped={!!qr.destination_url}
                                            />
                                        </div>
                                        <p className="truncate text-sm text-brand-muted">
                                            {qr.destination_url ??
                                                "Not mapped yet"}
                                        </p>
                                        {qr.shop_name && (
                                            <p className="truncate text-xs font-medium text-brand-text">
                                                Shop: {qr.shop_name}
                                            </p>
                                        )}
                                        {qr.design_names?.length > 0 && (
                                            <p className="truncate text-xs text-brand-muted">
                                                Design:{" "}
                                                {qr.design_names.join(", ")}
                                            </p>
                                        )}
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-xs text-brand-muted">
                                                {qr.batch_label} ·{" "}
                                                {qr.created_at}
                                            </p>
                                            <RowActions
                                                qr={qr}
                                                onEdit={setEditing}
                                                onPdf={printOne}
                                            />
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination paginator={codes} />
            </Panel>

            {editing && (
                <QrDetails
                    qr={editing}
                    shops={shops}
                    design={design}
                    onClose={() => setEditing(null)}
                    onPdf={printOne}
                    onDelete={deleteCodes}
                    confirm={confirm}
                />
            )}

            {confirmDialog}

            {scanning && (
                <QrStickerScanner onClose={() => setScanning(false)} />
            )}

            {printJob && (
                <PrintDialog
                    job={printJob}
                    designs={designs}
                    initial={{ designId, onePerPage, doubleSided, backId }}
                    onClose={() => setPrintJob(null)}
                    onPrint={confirmPrint}
                />
            )}
        </AdminLayout>
    );
}
