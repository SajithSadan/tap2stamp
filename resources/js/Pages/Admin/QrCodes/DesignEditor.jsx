import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    LuAlignCenterHorizontal,
    LuAlignCenterVertical,
    LuArrowLeft,
    LuCircleDot,
    LuFileDown,
    LuFrame,
    LuHash,
    LuImagePlus,
    LuLoaderCircle,
    LuPalette,
    LuPencil,
    LuRatio,
    LuReplace,
    LuSave,
    LuShapes,
    LuShieldCheck,
    LuTriangleAlert,
    LuType,
    LuX,
} from 'react-icons/lu';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { FieldError, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import QrDesignStage, { MIN_QR_SIZE, clampQr, clampRatio } from '@/Components/QrDesignStage';
import { artRect, designLayout, openPrintWindow, printQrPdf, shapeMismatch } from '@/lib/qrPrint';
import { useQrPreview } from '@/lib/qrRender';
import { blockAspect, effectiveEcc, formatSerial, qrFraction, scanWarnings } from '@/lib/qrStyle';
import useValidatedForm from '@/lib/useValidatedForm';
import { required } from '@/lib/validation';

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_LOGO_BYTES = 1024 * 1024;
const MIN_IMAGE_PX = 300;
const MIN_LOGO_PX = 64;

/** Quick colour schemes: [dots, corners, background]. */
const PALETTES = [
    ['Classic', '#000000', '#000000', '#FFFFFF'],
    ['Navy', '#0F2A46', '#0F2A46', '#FFFFFF'],
    ['Mint', '#0B3D2E', '#12A877', '#FFFFFF'],
    ['Plum', '#3B0A45', '#8E2C8A', '#FFF7FB'],
    ['Coffee', '#3E2723', '#6D4C41', '#FFF8F0'],
    ['Ocean', '#0B3C5D', '#2A78D6', '#F2F8FF'],
];

const round5 = (n) => Math.round(n * 100000) / 100000;

/**
 * Tallest the artwork is shown: the screen height minus the page header,
 * toolbar and canvas padding (about 20rem), but never below 20rem on short screens.
 */
const CANVAS_HEIGHT = 'max(20rem, 100dvh - 20rem)';

// --- Inspector building blocks ---------------------------------------------

/** Styling tabs in the right-hand panel. */
const TABS = [
    { key: 'colours', label: 'Colours', icon: LuPalette },
    { key: 'shape', label: 'Shape', icon: LuShapes },
    { key: 'frame', label: 'Frame', icon: LuFrame },
    { key: 'middle', label: 'Middle', icon: LuCircleDot },
    { key: 'caption', label: 'Caption', icon: LuType },
    { key: 'number', label: 'Number', icon: LuHash },
    { key: 'safety', label: 'Safety', icon: LuShieldCheck },
];

/** Which tab holds a field, so a validation error opens the right one. */
const TAB_FOR_FIELD = { logo: 'middle', 'style.center_text': 'middle', 'style.caption_text': 'caption' };

function InspectorTabs({ value, onChange }) {
    return (
        <div role="tablist" aria-label="Style" className="grid grid-cols-4 border-b border-brand-border sm:grid-cols-7 lg:grid-cols-4 2xl:grid-cols-7">
            {TABS.map(({ key, label, icon: Icon }) => (
                <button
                    key={key}
                    type="button"
                    role="tab"
                    aria-selected={value === key}
                    onClick={() => onChange(key)}
                    className={`flex flex-col items-center gap-1 border-b-2 px-1 py-2.5 text-[11px] font-semibold transition-colors ${
                        value === key ? 'border-brand-accent text-brand-text' : 'border-transparent text-brand-muted hover:text-brand-text'
                    }`}
                >
                    <Icon className="h-4 w-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}

function Pane({ children }) {
    return (
        <div role="tabpanel" className="space-y-4 p-5">
            {children}
        </div>
    );
}

/** Small caps label for a toolbar group. */
const toolbarLabel = 'text-[11px] font-semibold uppercase tracking-wide text-brand-muted';

/** Thin vertical line between toolbar groups (hidden when the toolbar wraps on phones). */
function ToolDivider() {
    return <span className="hidden h-6 w-px bg-brand-border sm:block" aria-hidden="true" />;
}

/**
 * A millimetre box for the toolbar. Applies on Enter or when you leave it
 * (not on every keystroke, so typing "25" doesn't jump through "2" first),
 * and shows the live value again whenever the box is dragged.
 */
function MmField({ id, label, value, onCommit, disabled }) {
    const shown = value === null ? '' : String(Math.round(value));
    const [text, setText] = useState(shown);
    const [focused, setFocused] = useState(false);
    useEffect(() => {
        if (!focused) setText(shown);
    }, [shown, focused]);

    function commit() {
        setFocused(false);
        const mm = Number(text);
        if (mm > 0 && String(Math.round(mm)) !== shown) onCommit(mm);
        else setText(shown);
    }

    return (
        <label htmlFor={id} className="flex items-center gap-1.5 text-sm text-brand-text">
            {label}
            <Suffixed suffix="mm" className="w-20">
                <input
                    id={id}
                    type="number"
                    min={1}
                    value={text}
                    disabled={disabled}
                    onFocus={() => setFocused(true)}
                    onChange={(e) => setText(e.target.value)}
                    onBlur={commit}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            e.currentTarget.blur();
                        }
                    }}
                    className="w-full min-w-0 bg-transparent py-1.5 pl-2.5 text-sm tabular-nums text-brand-text outline-none disabled:opacity-50"
                />
            </Suffixed>
        </label>
    );
}

/** An input with a unit ("mm") inside its right edge, styled like the other fields. */
function Suffixed({ suffix, className = '', children }) {
    return (
        <div
            className={`flex items-center rounded-xl border border-brand-border bg-brand-card transition focus-within:border-brand-accent focus-within:ring-4 focus-within:ring-brand-accent/10 ${className}`}
        >
            {children}
            <span className="pr-2.5 text-xs text-brand-muted">{suffix}</span>
        </div>
    );
}

/** Icon-only toolbar button; the label shows as a tooltip and is read by screen readers. */
function ToolButton({ label, icon: Icon, onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={label}
            aria-label={label}
            className="flex h-9 w-9 items-center justify-center rounded-lg border border-brand-border bg-brand-card text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text disabled:opacity-40"
        >
            <Icon className="h-4 w-4" />
        </button>
    );
}

function Label({ htmlFor, children, value }) {
    return (
        <label htmlFor={htmlFor} className="mb-1.5 flex items-baseline justify-between gap-2 text-sm font-medium text-brand-text">
            {/* One wrapper, so a label made of several pieces ("Prefix (optional)") stays together on the left. */}
            <span>{children}</span>
            {value !== undefined && <span className="text-xs font-normal tabular-nums text-brand-muted">{value}</span>}
        </label>
    );
}

function Segmented({ id, options, value, onChange, disabled = false }) {
    return (
        <div id={id} role="radiogroup" className="grid auto-cols-fr grid-flow-col gap-1 rounded-xl bg-brand-bg p-1 ring-1 ring-brand-border">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    role="radio"
                    aria-checked={value === o.value}
                    disabled={disabled}
                    onClick={() => onChange(o.value)}
                    className={`rounded-lg px-2 py-1.5 text-xs font-semibold transition-colors disabled:opacity-50 ${
                        value === o.value ? 'bg-brand-card text-brand-text ring-1 ring-brand-border' : 'text-brand-muted hover:text-brand-text'
                    }`}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

function Slider({ id, label, value, min, max, step = 0.005, format, onChange, disabled }) {
    return (
        <div>
            <Label htmlFor={id} value={format(value)}>
                {label}
            </Label>
            <input
                id={id}
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                disabled={disabled}
                onChange={(e) => onChange(Number(e.target.value))}
                className="w-full accent-[var(--color-brand-accent)] disabled:opacity-50"
            />
        </div>
    );
}

function ColorField({ id, label, value, onChange, disabled }) {
    const [text, setText] = useState(value);
    useEffect(() => setText(value), [value]);

    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    value={value}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.value.toUpperCase())}
                    aria-label={`${label} colour picker`}
                    className="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-brand-border bg-brand-card p-1 disabled:opacity-50"
                />
                <input
                    id={id}
                    value={text}
                    disabled={disabled}
                    maxLength={7}
                    onChange={(e) => {
                        const next = e.target.value.toUpperCase();
                        setText(next);
                        if (/^#[0-9A-F]{6}$/.test(next)) onChange(next);
                    }}
                    onBlur={() => setText(value)}
                    className={`${inputClass} font-mono uppercase`}
                />
            </div>
        </div>
    );
}

function Toggle({ id, label, checked, onChange }) {
    return (
        <label htmlFor={id} className="flex cursor-pointer items-center justify-between gap-3 text-sm text-brand-text">
            {label}
            <input id={id} type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 accent-[var(--color-brand-accent)]" />
        </label>
    );
}

/** Reads a picked image file: checks type/size/pixels, returns { url, width, height } or an error message. */
function readImageFile(file, { maxBytes, minPx, tooBig }) {
    return new Promise((resolve) => {
        if (!IMAGE_TYPES.includes(file.type)) return resolve({ error: 'Upload a JPG, PNG or WebP image.' });
        if (file.size > maxBytes) return resolve({ error: tooBig });

        const url = URL.createObjectURL(file);
        const probe = new Image();
        probe.onload = () => {
            if (probe.naturalWidth < minPx || probe.naturalHeight < minPx) {
                URL.revokeObjectURL(url);
                return resolve({ error: `That image is too small. Use one at least ${minPx} × ${minPx} pixels.` });
            }
            resolve({ url, width: probe.naturalWidth, height: probe.naturalHeight });
        };
        probe.onerror = () => {
            URL.revokeObjectURL(url);
            resolve({ error: "That file couldn't be read as an image." });
        };
        probe.src = url;
    });
}

// --- Page --------------------------------------------------------------------

export default function DesignEditor({ design, defaults, limits, presets }) {
    const editing = !!design;
    const imageInput = useRef(null);
    const logoInput = useRef(null);
    const [image, setImage] = useState(design ? { url: design.image_url, width: design.image_width, height: design.image_height } : null);
    const [logoUrl, setLogoUrl] = useState(design?.logo_url ?? null);
    const [fileError, setFileError] = useState({});
    const [testing, setTesting] = useState(false);
    const [tab, setTab] = useState('colours');

    const form = useValidatedForm(
        {
            name: design?.name ?? '',
            image: null,
            logo: null,
            remove_logo: false,
            qr_x: design?.qr_x ?? 0.35,
            qr_y: design?.qr_y ?? 0.35,
            qr_size: design?.qr_size ?? 0.3,
            // New designs start as a Stand (9 x 14 cm); existing ones keep their size.
            preset: design ? design.preset : 'stand',
            width_mm: design?.width_mm ?? presets.stand.width_mm,
            height_mm: design ? design.height_mm : presets.stand.height_mm,
            style: design?.style ?? defaults,
        },
        {
            rules: {
                name: [required('Give the design a name.')],
                image: [(value) => (!editing && !value ? 'Upload a background image.' : null)],
                logo: [(value, d) => (d.style.center_type === 'logo' && !value && !logoUrl ? 'Upload a logo for the middle of the QR.' : null)],
                'style.center_text': [(v, d) => (d.style.center_type === 'text' && !d.style.center_text.trim() ? 'Type the text for the middle of the QR.' : null)],
                'style.caption_text': [(v, d) => (d.style.caption_position !== 'none' && !d.style.caption_text.trim() ? 'Type the caption, or turn the caption off.' : null)],
            },
        },
    );
    const { data, setData, errors, processing } = form;
    const style = data.style;

    const aspect = image ? image.height / image.width : 1;
    const block = blockAspect(style);
    const qr = { x: data.qr_x, y: data.qr_y, size: data.qr_size };
    const qrSrc = useQrPreview(style, style.center_type === 'logo' ? logoUrl : null, 560);

    // Blob previews are freed when replaced.
    useEffect(() => () => image?.url.startsWith('blob:') && URL.revokeObjectURL(image.url), [image]);
    useEffect(() => () => logoUrl?.startsWith('blob:') && URL.revokeObjectURL(logoUrl), [logoUrl]);

    // A problem with a field on another tab (e.g. empty caption) opens that tab.
    useEffect(() => {
        const field = Object.keys(errors).find((key) => TAB_FOR_FIELD[key]);
        if (field) setTab(TAB_FOR_FIELD[field]);
    }, [errors]);

    /** Moves/resizes the box; `ratio` (height / width) is passed only when its shape changes. */
    const setQr = (next, ratio) =>
        setData((prev) => ({
            ...prev,
            qr_x: next.x,
            qr_y: next.y,
            qr_size: next.size,
            style: ratio === undefined ? prev.style : { ...prev.style, box_ratio: round5(ratio) },
        }));

    /** Width or height of the box in printed mm, changing just that side. */
    function setBoxMm(side, mm) {
        if (!layout || !(mm > 0)) return;
        const heightMm = qr.size * block * artW;
        if (side === 'w') {
            const size = Math.min(Math.max(mm / artW, MIN_QR_SIZE), 1 - qr.x);
            const next = { ...qr, size };
            setQr(next, clampRatio(heightMm / (size * artW), next, aspect));
        } else {
            setQr(qr, clampRatio(mm / (qr.size * artW), qr, aspect));
        }
    }

    /** Changes style keys, keeping the block on the image (a caption makes it taller). */
    function setStyle(patch) {
        setData((prev) => {
            const nextStyle = { ...prev.style, ...patch };
            const spot = clampQr({ x: prev.qr_x, y: prev.qr_y, size: prev.qr_size }, aspect, blockAspect(nextStyle));
            return { ...prev, style: nextStyle, qr_x: spot.x, qr_y: spot.y, qr_size: spot.size };
        });
    }

    async function pickImage(file) {
        setFileError((e) => ({ ...e, image: null }));
        if (!file) return;
        const result = await readImageFile(file, { maxBytes: MAX_IMAGE_BYTES, minPx: MIN_IMAGE_PX, tooBig: 'That image is over 5 MB. Try a smaller one.' });
        if (result.error) return setFileError((e) => ({ ...e, image: result.error }));

        const nextAspect = result.height / result.width;
        setImage(result);
        setData((prev) => {
            const start = editing ? { x: prev.qr_x, y: prev.qr_y, size: prev.qr_size } : { x: 0.35, y: 0.3, size: 0.3 };
            const spot = clampQr(start, nextAspect, blockAspect(prev.style));
            if (!editing) {
                // New design: centre it.
                spot.x = (1 - spot.size) / 2;
                spot.y = (1 - (spot.size * blockAspect(prev.style)) / nextAspect) / 2;
            }
            return { ...prev, image: file, qr_x: spot.x, qr_y: spot.y, qr_size: spot.size, name: prev.name || file.name.replace(/\.[^.]+$/, '') };
        });
    }

    async function pickLogo(file) {
        setFileError((e) => ({ ...e, logo: null }));
        if (!file) return;
        const result = await readImageFile(file, { maxBytes: MAX_LOGO_BYTES, minPx: MIN_LOGO_PX, tooBig: 'That logo is over 1 MB. Try a smaller one.' });
        if (result.error) return setFileError((e) => ({ ...e, logo: result.error }));

        setLogoUrl(result.url);
        setData((prev) => ({ ...prev, logo: file, remove_logo: false }));
    }

    function removeLogo() {
        setLogoUrl(null);
        setData((prev) => ({ ...prev, logo: null, remove_logo: true, style: { ...prev.style, center_type: prev.style.center_type === 'logo' ? 'none' : prev.style.center_type } }));
    }

    function align(axis) {
        setQr(
            axis === 'x'
                ? { ...qr, x: (1 - qr.size) / 2 }
                : { ...qr, y: (1 - (qr.size * block) / aspect) / 2 },
        );
    }

    function submit(e) {
        e?.preventDefault();
        form.transform((d) => ({
            ...d,
            qr_x: round5(d.qr_x),
            qr_y: round5(d.qr_y),
            qr_size: round5(d.qr_size),
            remove_logo: d.remove_logo ? 1 : 0,
            // One JSON field keeps the nested style intact through the multipart upload.
            style: JSON.stringify(d.style),
            // Always sent, so switching to Custom clears the preset / an empty height means "follow the artwork".
            preset: d.preset ?? '',
            height_mm: d.height_mm ?? '',
            // Files can't go in a real PUT body, so updates are a POST that says it's a PUT.
            ...(editing ? { _method: 'put' } : {}),
        }));
        form.post(editing ? `/admin/qr-codes/designs/${design.id}` : '/admin/qr-codes/designs', {
            forceFormData: true,
            // Keep what's on screen if the server finds a problem; after a save, start
            // fresh from the saved design (so the uploads aren't sent again).
            preserveState: 'errors',
        });
    }

    /** Picks a print size: a preset (fixed W x H) or custom (keeps the current numbers, editable). */
    function choosePreset(key) {
        setData((prev) =>
            key === 'custom'
                ? { ...prev, preset: null }
                : { ...prev, preset: key, width_mm: presets[key].width_mm, height_mm: presets[key].height_mm },
        );
    }

    // Print maths, with the size boxes clamped while they're being typed in.
    const clampMm = (value, min, max) => Math.min(Math.max(Number(value) || min, min), max);
    const widthMm = clampMm(data.width_mm, limits.min_width_mm, limits.max_width_mm);
    const heightMm = data.height_mm === null || data.height_mm === '' ? null : clampMm(data.height_mm, limits.min_height_mm, limits.max_height_mm);
    const current = image
        ? { ...data, width_mm: widthMm, height_mm: heightMm, image_url: image.url, image_width: image.width, image_height: image.height, logo_url: logoUrl }
        : null;
    const layout = current ? designLayout(current) : null;
    // The artwork's printed rectangle inside the sticker (fitted, never stretched) - the QR is sized against it.
    const art = layout ? artRect(layout.w, layout.h, aspect) : null;
    const artW = art ? art.w : 0;
    const qrMm = art ? data.qr_size * qrFraction(style) * artW : 0;
    const tooSmall = layout && qrMm < limits.min_qr_mm;
    const misfit = current && shapeMismatch(current) > 0.03;
    const warnings = useMemo(() => scanWarnings(style), [style]);

    async function testPrint() {
        const win = openPrintWindow();
        setTesting(true);
        try {
            await printQrPdf({ title: `${data.name || 'Design'} - test print`, codes: [{ scan_url: `${window.location.origin}/qr/SAMPLE`, serial: 1 }], design: current, onePerPage: true }, win);
        } finally {
            setTesting(false);
        }
    }

    const pct = (v) => `${Math.round(v * 100)}%`;

    return (
        <AdminLayout
            title={editing ? 'Edit sticker design' : 'New sticker design'}
            description="Place a styled QR on your artwork. Every printed code gets its own QR in this exact spot."
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link href="/admin/qr-codes/designs" className={secondaryButton}>
                        <LuArrowLeft className="h-4 w-4" /> All designs
                    </Link>
                    <button type="button" onClick={testPrint} disabled={!current || testing} className={secondaryButton}>
                        {testing ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuFileDown className="h-4 w-4" />} Test print
                    </button>
                    <button type="button" onClick={submit} disabled={processing} className={primaryButton}>
                        <LuSave className="h-4 w-4" /> {processing ? 'Saving…' : editing ? 'Save changes' : 'Save design'}
                    </button>
                </div>
            }
        >
            <input ref={imageInput} type="file" accept={IMAGE_TYPES.join(',')} className="sr-only" tabIndex={-1} onChange={(e) => { pickImage(e.target.files?.[0]); e.target.value = ''; }} />
            <input ref={logoInput} type="file" accept={IMAGE_TYPES.join(',')} className="sr-only" tabIndex={-1} onChange={(e) => { pickLogo(e.target.files?.[0]); e.target.value = ''; }} />

            <form onSubmit={submit} noValidate className="space-y-4">
                {/* Toolbar: one slim row, like a design app - name, print size, then the QR box once there's artwork. */}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-2xl border border-brand-border bg-brand-card px-3 py-2">
                    <label
                        htmlFor="name"
                        title="Design name"
                        className="flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-brand-bg focus-within:bg-brand-bg focus-within:ring-2 focus-within:ring-brand-accent/30"
                    >
                        <LuPencil className="h-3.5 w-3.5 shrink-0 text-brand-muted" />
                        <input
                            id="name"
                            value={data.name}
                            maxLength={100}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Untitled design"
                            aria-label="Design name"
                            className="w-40 bg-transparent text-sm font-semibold text-brand-text outline-none placeholder:font-medium placeholder:text-brand-muted sm:w-52"
                        />
                    </label>

                    <ToolDivider />

                    <div className="flex flex-wrap items-center gap-2">
                        <span className={toolbarLabel} title="The printed size of the whole sticker. Your artwork is fitted inside it, never stretched.">
                            Print size
                        </span>
                        <div className="flex rounded-lg bg-brand-bg p-0.5 ring-1 ring-brand-border" role="radiogroup" aria-label="Print size">
                            {[
                                ...Object.entries(presets).map(([key, p]) => [key, p.label, `${p.width_mm / 10}×${p.height_mm / 10} cm`]),
                                ['custom', 'Custom', null],
                            ].map(([key, label, size]) => {
                                const active = (data.preset ?? 'custom') === key;
                                return (
                                    <button
                                        key={key}
                                        type="button"
                                        role="radio"
                                        aria-checked={active}
                                        onClick={() => choosePreset(key)}
                                        className={`whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                                            active ? 'bg-brand-card text-brand-text ring-1 ring-brand-border' : 'text-brand-muted hover:text-brand-text'
                                        }`}
                                    >
                                        {label}
                                        {size && <span className="ml-1 font-normal tabular-nums text-brand-muted">{size}</span>}
                                    </button>
                                );
                            })}
                        </div>
                        {data.preset === null && (
                            <div className="flex items-center gap-1.5">
                                <Suffixed suffix="mm" className="w-20">
                                    <input
                                        id="width_mm"
                                        type="number"
                                        aria-label="Sticker width in mm"
                                        min={limits.min_width_mm}
                                        max={limits.max_width_mm}
                                        value={data.width_mm}
                                        onChange={(e) => setData('width_mm', e.target.value === '' ? '' : Number(e.target.value))}
                                        className="w-full min-w-0 bg-transparent py-1.5 pl-2.5 text-sm tabular-nums text-brand-text outline-none"
                                    />
                                </Suffixed>
                                <span className="text-brand-muted">×</span>
                                <Suffixed suffix="mm" className="w-20">
                                    <input
                                        id="height_mm"
                                        type="number"
                                        aria-label="Sticker height in mm (empty = follow the artwork)"
                                        title="Leave empty to follow your artwork's shape"
                                        placeholder="auto"
                                        min={limits.min_height_mm}
                                        max={limits.max_height_mm}
                                        value={data.height_mm ?? ''}
                                        onChange={(e) => setData('height_mm', e.target.value === '' ? null : Number(e.target.value))}
                                        className="w-full min-w-0 bg-transparent py-1.5 pl-2.5 text-sm tabular-nums text-brand-text outline-none placeholder:text-brand-muted/70"
                                    />
                                </Suffixed>
                            </div>
                        )}
                    </div>

                    {image && (
                        <>
                            <ToolDivider />
                            <div className="flex flex-wrap items-center gap-2.5" role="group" aria-label="QR box">
                                <span className={toolbarLabel}>QR box</span>
                                <input
                                    id="qr_size"
                                    type="range"
                                    min={0.05}
                                    max={Math.min(1, aspect / block)}
                                    step={0.005}
                                    value={data.qr_size}
                                    onChange={(e) => setQr(clampQr({ ...qr, size: Number(e.target.value) }, aspect, block))}
                                    aria-label="QR box size (keeps its shape)"
                                    title="Size (keeps its shape)"
                                    className="w-24 accent-[var(--color-brand-accent)]"
                                />
                                <MmField id="box_w" label="W" value={qr.size * artW} onCommit={(mm) => setBoxMm('w', mm)} />
                                <MmField id="box_h" label="H" value={qr.size * block * artW} onCommit={(mm) => setBoxMm('h', mm)} />
                                <div className="flex gap-1">
                                    <ToolButton label="Auto height (fit the QR)" icon={LuRatio} disabled={style.box_ratio === null} onClick={() => setStyle({ box_ratio: null })} />
                                    <ToolButton label="Centre across" onClick={() => align('x')} icon={LuAlignCenterVertical} />
                                    <ToolButton label="Centre down" onClick={() => align('y')} icon={LuAlignCenterHorizontal} />
                                </div>
                            </div>
                        </>
                    )}

                    <button type="button" onClick={() => imageInput.current?.click()} className={`${secondaryButton} ml-auto py-1.5 text-xs`}>
                        <LuReplace className="h-3.5 w-3.5" /> {image ? 'Replace artwork' : 'Upload artwork'}
                    </button>
                </div>

                {(misfit || errors.name || errors.width_mm || errors.height_mm || errors.preset || errors.qr_size) && (
                    <div className="-mt-1 space-y-1 px-1">
                        {misfit && art && (
                            <p className="flex items-start gap-2 text-xs text-amber-700">
                                <LuTriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" />
                                Your artwork isn’t the same shape as {Math.round(layout.w)} × {Math.round(layout.h)} mm, so it prints at {Math.round(art.w)} × {Math.round(art.h)} mm,
                                centred, with plain edges. For edge-to-edge, use artwork in that shape (or pick Custom with an empty height).
                            </p>
                        )}
                        <FieldError message={errors.name} />
                        <FieldError message={errors.width_mm} />
                        <FieldError message={errors.height_mm} />
                        <FieldError message={errors.preset} />
                        <FieldError message={errors.qr_size} />
                    </div>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] 2xl:grid-cols-[minmax(0,1fr)_23rem]">
                {/* Canvas, with a status bar underneath */}
                <div className="lg:sticky lg:top-6">
                    <div className="overflow-hidden rounded-2xl border border-brand-border">
                        <div className="flex min-h-[26rem] items-center justify-center bg-[repeating-conic-gradient(#f1f4f7_0%_25%,#fafbfc_0%_50%)] bg-[length:20px_20px] p-6 sm:p-8">
                            {image ? (
                                // Fit the whole design on screen: as wide as the canvas allows, but never
                                // taller than the space under the toolbar (so tall artwork shrinks to fit).
                                <div style={{ width: `min(100%, calc(${CANVAS_HEIGHT} / ${aspect}))` }}>
                                    <QrDesignStage imageUrl={image.url} aspect={aspect} block={block} qr={qr} qrSrc={qrSrc} serialStyle={style} onChange={setQr} editable />
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    id="image"
                                    onClick={() => imageInput.current?.click()}
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        pickImage(e.dataTransfer.files?.[0]);
                                    }}
                                    className="flex w-full max-w-md flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-brand-border bg-brand-card px-6 py-14 text-center transition-colors hover:border-brand-accent"
                                >
                                    <LuImagePlus className="h-9 w-9 text-brand-accent" />
                                    <span className="text-sm font-semibold text-brand-text">Upload your background artwork</span>
                                    <span className="text-xs text-brand-muted">
                                        JPG, PNG or WebP · up to 5 MB · at least {MIN_IMAGE_PX} × {MIN_IMAGE_PX} px. Or drop it here.
                                    </span>
                                </button>
                            )}
                        </div>

                        {layout && (
                            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-t border-brand-border bg-brand-card px-4 py-2 text-xs text-brand-muted">
                                <span>Drag to move · edges change width or height · corner resizes · arrow keys nudge</span>
                                <span className="flex gap-4 tabular-nums">
                                    <span title="Printed size of the whole sticker">
                                        Sticker <strong className="font-semibold text-brand-text">{Math.round(layout.w)} × {Math.round(layout.h)} mm</strong>
                                    </span>
                                    <span title="Stickers per A4 page when printed as a sheet">
                                        <strong className="font-semibold text-brand-text">{layout.perPage}</strong> per A4
                                    </span>
                                    <span title={`At least ${limits.min_qr_mm} mm scans reliably`}>
                                        QR prints <strong className={`font-semibold ${tooSmall ? 'text-red-600' : 'text-brand-text'}`}>{Math.round(qrMm)} mm</strong>
                                    </span>
                                </span>
                            </div>
                        )}
                    </div>
                    <FieldError message={fileError.image ?? errors.image} />
                </div>

                {/* Inspector */}
                <aside className="overflow-hidden rounded-2xl border border-brand-border bg-brand-card lg:sticky lg:top-6">
                    <InspectorTabs value={tab} onChange={setTab} />

                    {tab === 'colours' && (
                    <Pane>
                        <div className="grid grid-cols-3 gap-2">
                            {PALETTES.map(([name, fg, eye, bg]) => (
                                <button
                                    key={name}
                                    type="button"
                                    // Leaves "See-through background" as it is - the palette's background
                                    // colour is kept for when see-through is turned off.
                                    onClick={() => setStyle({ fg, eye_color: eye, bg })}
                                    className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium text-brand-text ring-1 ring-brand-border hover:ring-brand-accent"
                                >
                                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full ring-1 ring-brand-border" style={{ background: bg }}>
                                        <span className="h-2.5 w-2.5 rounded-full" style={{ background: eye }} />
                                    </span>
                                    {name}
                                </button>
                            ))}
                        </div>
                        <ColorField id="style.fg" label="Dots" value={style.fg} onChange={(fg) => setStyle({ fg })} />
                        <ColorField id="style.eye_color" label="Corner squares" value={style.eye_color} onChange={(eye_color) => setStyle({ eye_color })} />
                        <ColorField id="style.bg" label="Background" value={style.bg} disabled={style.transparent} onChange={(bg) => setStyle({ bg })} />
                        <Toggle id="style.transparent" label="See-through background" checked={style.transparent} onChange={(transparent) => setStyle({ transparent })} />
                    </Pane>
                    )}

                    {tab === 'shape' && (
                    <Pane>
                        <div>
                            <Label htmlFor="style.modules">Dots</Label>
                            <Segmented
                                id="style.modules"
                                value={style.modules}
                                onChange={(modules) => setStyle({ modules })}
                                options={[
                                    { value: 'square', label: 'Square' },
                                    { value: 'rounded', label: 'Rounded' },
                                    { value: 'dots', label: 'Dots' },
                                ]}
                            />
                        </div>
                        <div>
                            <Label htmlFor="style.eyes">Corners</Label>
                            <Segmented
                                id="style.eyes"
                                value={style.eyes}
                                onChange={(eyes) => setStyle({ eyes })}
                                options={[
                                    { value: 'square', label: 'Square' },
                                    { value: 'rounded', label: 'Rounded' },
                                    { value: 'circle', label: 'Circle' },
                                ]}
                            />
                        </div>
                    </Pane>
                    )}

                    {tab === 'frame' && (
                    <Pane>
                        <Slider
                            id="style.padding"
                            label="Quiet zone (space around the QR)"
                            value={style.padding}
                            min={0}
                            max={0.2}
                            format={(v) => (v ? pct(v) : 'None')}
                            onChange={(padding) => setStyle({ padding })}
                        />
                        <p className="-mt-2 text-xs text-brand-muted">
                            Set to None if your artwork already has a plain area around the QR. Turn on “See-through background” (Colours) to drop the white box too.
                        </p>
                        <Slider id="style.border_width" label="Border" value={style.border_width} min={0} max={0.08} format={(v) => (v ? pct(v) : 'None')} onChange={(border_width) => setStyle({ border_width })} />
                        {style.border_width > 0 && <ColorField id="style.border_color" label="Border colour" value={style.border_color} onChange={(border_color) => setStyle({ border_color })} />}
                        <Slider id="style.radius" label="Rounded corners" value={style.radius} min={0} max={0.25} format={(v) => (v ? pct(v) : 'Square')} onChange={(radius) => setStyle({ radius })} />
                    </Pane>
                    )}

                    {tab === 'middle' && (
                    <Pane>
                        <Segmented
                            id="style.center_type"
                            value={style.center_type}
                            onChange={(center_type) => setStyle({ center_type })}
                            options={[
                                { value: 'none', label: 'Nothing' },
                                { value: 'text', label: 'Text' },
                                { value: 'logo', label: 'Logo' },
                            ]}
                        />
                        {style.center_type === 'text' && (
                            <div>
                                <Label htmlFor="style.center_text" value={`${style.center_text.length}/12`}>
                                    Text
                                </Label>
                                <input id="style.center_text" value={style.center_text} maxLength={12} onChange={(e) => setStyle({ center_text: e.target.value })} placeholder="e.g. SCAN" className={inputClass} />
                                <FieldError message={errors['style.center_text']} />
                            </div>
                        )}
                        {style.center_type === 'logo' && (
                            <div id="logo">
                                {logoUrl ? (
                                    <div className="flex items-center gap-3">
                                        <img src={logoUrl} alt="Logo" className="h-12 w-12 rounded-lg object-contain ring-1 ring-brand-border" />
                                        <button type="button" onClick={() => logoInput.current?.click()} className={secondaryButton}>
                                            Replace
                                        </button>
                                        <button type="button" onClick={removeLogo} aria-label="Remove logo" className="rounded-lg p-2 text-red-600 hover:bg-red-50">
                                            <LuX className="h-4 w-4" />
                                        </button>
                                    </div>
                                ) : (
                                    <button type="button" onClick={() => logoInput.current?.click()} className={`${secondaryButton} w-full`}>
                                        <LuImagePlus className="h-4 w-4" /> Upload logo
                                    </button>
                                )}
                                <p className="mt-1.5 text-xs text-brand-muted">Square works best. PNG with a see-through background looks cleanest. Up to 1 MB.</p>
                                <FieldError message={fileError.logo ?? errors.logo} />
                            </div>
                        )}
                        {style.center_type !== 'none' && (
                            <>
                                <Slider id="style.center_size" label="Size" value={style.center_size} min={0.1} max={0.3} format={pct} onChange={(center_size) => setStyle({ center_size })} />
                                <div className="grid grid-cols-2 gap-3">
                                    {style.center_type === 'text' && <ColorField id="style.center_color" label="Text" value={style.center_color} onChange={(center_color) => setStyle({ center_color })} />}
                                    <ColorField id="style.center_bg" label="Card" value={style.center_bg} onChange={(center_bg) => setStyle({ center_bg })} />
                                </div>
                            </>
                        )}
                    </Pane>
                    )}

                    {tab === 'caption' && (
                    <Pane>
                        <Segmented
                            id="style.caption_position"
                            value={style.caption_position}
                            onChange={(caption_position) => setStyle({ caption_position, caption_text: style.caption_text || (caption_position !== 'none' ? 'Scan me' : '') })}
                            options={[
                                { value: 'none', label: 'Off' },
                                { value: 'above', label: 'Above' },
                                { value: 'below', label: 'Below' },
                            ]}
                        />
                        {style.caption_position !== 'none' && (
                            <>
                                <div>
                                    <Label htmlFor="style.caption_text" value={`${style.caption_text.length}/40`}>
                                        Text
                                    </Label>
                                    <input id="style.caption_text" value={style.caption_text} maxLength={40} onChange={(e) => setStyle({ caption_text: e.target.value })} className={inputClass} />
                                    <FieldError message={errors['style.caption_text']} />
                                </div>
                                <Slider id="style.caption_size" label="Text size" value={style.caption_size} min={0.05} max={0.16} format={pct} onChange={(caption_size) => setStyle({ caption_size })} />
                                <ColorField id="style.caption_color" label="Text colour" value={style.caption_color} onChange={(caption_color) => setStyle({ caption_color })} />
                                <Toggle id="style.caption_bold" label="Bold" checked={style.caption_bold} onChange={(caption_bold) => setStyle({ caption_bold })} />
                            </>
                        )}
                        <div>
                            <Label htmlFor="style.font">Font (caption and middle text)</Label>
                            <Segmented
                                id="style.font"
                                value={style.font}
                                onChange={(font) => setStyle({ font })}
                                options={[
                                    { value: 'sans', label: 'Modern' },
                                    { value: 'serif', label: 'Classic' },
                                    { value: 'mono', label: 'Mono' },
                                ]}
                            />
                        </div>
                    </Pane>
                    )}

                    {tab === 'number' && (
                    <Pane>
                        <Toggle
                            id="style.serial_enabled"
                            label="Print a serial number"
                            checked={style.serial_enabled}
                            onChange={(serial_enabled) => setStyle({ serial_enabled })}
                        />
                        <p className="-mt-2 text-xs text-brand-muted">
                            Each sticker gets its code’s position in its batch (001, 002, 003 …) at the bottom centre. A reprint of one code keeps its number.
                        </p>
                        {style.serial_enabled && (
                            <>
                                <div>
                                    <Label htmlFor="style.serial_prefix" value={`${style.serial_prefix.length}/8`}>
                                        Prefix <span className="font-normal text-brand-muted">(optional)</span>
                                    </Label>
                                    <input
                                        id="style.serial_prefix"
                                        value={style.serial_prefix}
                                        maxLength={8}
                                        onChange={(e) => setStyle({ serial_prefix: e.target.value })}
                                        placeholder="e.g. No. "
                                        className={inputClass}
                                    />
                                    <p className="mt-1 text-xs text-brand-muted">
                                        Prints as <span className="font-mono font-semibold text-brand-text">{formatSerial(1, style)}</span>
                                    </p>
                                </div>
                                <Slider id="style.serial_size" label="Text size" value={style.serial_size} min={0.02} max={0.12} format={pct} onChange={(serial_size) => setStyle({ serial_size })} />
                                <Slider
                                    id="style.serial_offset"
                                    label="Distance from the bottom"
                                    value={style.serial_offset}
                                    min={0}
                                    max={0.5}
                                    format={pct}
                                    onChange={(serial_offset) => setStyle({ serial_offset })}
                                />
                                <ColorField id="style.serial_color" label="Colour" value={style.serial_color} onChange={(serial_color) => setStyle({ serial_color })} />
                                <Toggle id="style.serial_bold" label="Bold" checked={style.serial_bold} onChange={(serial_bold) => setStyle({ serial_bold })} />
                            </>
                        )}
                    </Pane>
                    )}

                    {tab === 'safety' && (
                    <Pane>
                        <div>
                            <Label htmlFor="style.ecc">Error correction</Label>
                            <Segmented
                                id="style.ecc"
                                value={effectiveEcc(style)}
                                disabled={style.center_type !== 'none'}
                                onChange={(ecc) => setStyle({ ecc })}
                                options={[
                                    { value: 'M', label: 'Standard' },
                                    { value: 'Q', label: 'High' },
                                    { value: 'H', label: 'Highest' },
                                ]}
                            />
                            <p className="mt-1.5 text-xs text-brand-muted">
                                {style.center_type !== 'none'
                                    ? 'Set to Highest automatically, so the QR still scans with something in the middle.'
                                    : 'Higher copes better with scratches and dirt, but packs in more, smaller dots.'}
                            </p>
                        </div>
                    </Pane>
                    )}

                    {(warnings.length > 0 || tooSmall) && (
                        <div role="status" className="space-y-2 border-t border-brand-border px-5 py-4">
                            {tooSmall && (
                                <p className="flex gap-2 text-xs font-medium text-red-600">
                                    <LuTriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" />
                                    The QR prints only {Math.round(qrMm)} mm wide. Make it at least {limits.min_qr_mm} mm (bigger QR, thinner frame or a wider sticker).
                                </p>
                            )}
                            {warnings.map((w) => (
                                <p key={w} className="flex gap-2 text-xs text-amber-700">
                                    <LuTriangleAlert className="mt-px h-3.5 w-3.5 shrink-0" />
                                    {w}
                                </p>
                            ))}
                        </div>
                    )}
                </aside>
                </div>
            </form>
        </AdminLayout>
    );
}
