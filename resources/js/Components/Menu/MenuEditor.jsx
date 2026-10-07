import { Link, router } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useMemo, useRef, useState } from "react";
import {
    LuArrowDown,
    LuArrowUp,
    LuCheck,
    LuChevronDown,
    LuExternalLink,
    LuFileText,
    LuImage,
    LuImagePlus,
    LuImageUp,
    LuListChecks,
    LuLoaderCircle,
    LuMinus,
    LuPalette,
    LuPlus,
    LuSparkles,
    LuTag,
    LuTrash2,
    LuX,
} from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import { FieldError, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";
import PhotoFinder from "@/Components/Menu/PhotoFinder";
import MenuView, { LAYOUT_LABELS } from "@/Components/MenuView";
import { hasTag, MAX_TAGS, TAG_LENGTH, tagSuggestions, tidyTag } from "@/lib/menuTags";
import { useThemeFonts } from "@/lib/theme";

let nextKey = 0;
const key = () => `k${++nextKey}`;
const blankItem = () => ({ key: key(), name: "", description: "", price: "", tags: [] });

const PHOTO_FIELDS = ["id", "image_path", "image_url", "image_status", "image_reason"];

/** React keys for every section and item (stripped again before saving). Photo fields ride along when known. */
function withKeys(sections) {
    return sections.map((section) => ({
        key: key(),
        name: section.name ?? "",
        items: (section.items ?? []).map((item) => ({
            key: key(),
            name: item.name ?? "",
            description: item.description ?? "",
            price: item.price ?? "",
            tags: item.tags ?? [],
            ...Object.fromEntries(PHOTO_FIELDS.filter((f) => item[f] !== undefined).map((f) => [f, item[f]])),
        })),
    }));
}

/**
 * What's saved (and drawn in the preview). `image_path` is only sent when
 * known: the server keeps a photo only if it's one this shop already had,
 * null takes it off, and no key at all keeps the same-named item's photo
 * (e.g. after re-importing from a photo of the menu).
 */
function withoutKeys(sections) {
    return sections.map(({ name, items }) => ({
        name,
        items: items.map(({ name, description, price, tags, image_path, image_url }) => ({
            name,
            description,
            price,
            tags,
            ...(image_path !== undefined && { image_path }),
            ...(image_url !== undefined && { image_url }),
        })),
    }));
}

/** Puts fetched photos into the editor rows (matched by section + item name). */
function withPhotos(sections, found) {
    if (!found.length) return sections;
    const lookup = new Map(found.map((r) => [`${r.section}\u0000${r.name}`, r]));

    return sections.map((section) => ({
        ...section,
        items: section.items.map((item) => {
            const r = lookup.get(`${section.name}\u0000${item.name}`);
            return r ? { ...item, image_path: r.image_path, image_url: r.image_url, image_status: r.image_status, image_reason: r.image_reason } : item;
        }),
    }));
}

const needsPhoto = (sections) => sections.some((s) => s.items.some((i) => i.id && i.image_status === null));

function move(list, index, by) {
    const target = index + by;
    if (target < 0 || target >= list.length) return list;
    const copy = [...list];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    return copy;
}

/** Shown on the theme cards while the shop has no menu yet. */
const SAMPLE = [
    {
        name: "Hot drinks",
        items: [
            { name: "Flat white", description: "Double ristretto, steamed milk", price: "£3.40", tags: [] },
            { name: "Chai latte", description: "Spiced, with oat milk", price: "£3.60", tags: ["Vegan"] },
        ],
    },
    { name: "Bakes", items: [{ name: "Almond croissant", description: null, price: "£3.10", tags: ["Contains nuts"] }] },
];

/**
 * Phone photos are often 5-10 MB. Shrinks them to ≤ 2400 px JPEG before
 * upload - still sharp enough to read, and well inside Gemini's limit.
 * PDFs (and anything the browser can't draw) go up as they are.
 */
async function shrink(file) {
    if (!file.type.startsWith("image/")) return file;

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, 2400 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext("2d").drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.85));

        return blob && blob.size < file.size
            ? new File([blob], file.name.replace(/\.\w+$/, "") + ".jpg", { type: "image/jpeg" })
            : file;
    } catch {
        return file;
    }
}

const ACCEPTED = ["image/jpeg", "image/png", "image/webp", "application/pdf"];

const iconButton =
    "inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text disabled:opacity-30";

/**
 * Inputs that look like text until hovered / focused. No width here: callers
 * add w-full / flex-1 / w-28 (a w-full in here would beat w-28 and squash the name).
 */
const quietInput =
    "min-w-0 rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-sm text-brand-text outline-none transition placeholder:text-brand-muted/60 hover:border-brand-border focus:border-brand-accent focus:bg-brand-card focus:ring-4 focus:ring-brand-accent/10";

const errorRing = "!border-red-400";

/* ---------- Import with AI ---------- */

/**
 * Photos / a PDF of the printed menu → Gemini → the editor (nothing is saved
 * until "Save menu"). `hero`: the big drop area while the menu is empty.
 */
function ImportBox({ readUrl, maxFiles, hasMenu, hero, onRead, onClose }) {
    const inputRef = useRef(null);
    const [files, setFiles] = useState([]);
    const [mode, setMode] = useState("replace");
    const [reading, setReading] = useState(false);
    const [dragging, setDragging] = useState(false);
    const [error, setError] = useState(null);

    function addFiles(list) {
        setError(null);
        const accepted = Array.from(list).filter((f) => ACCEPTED.includes(f.type));
        if (accepted.length < list.length) setError("Only JPG, PNG, WebP or PDF files.");
        setFiles((current) => [...current, ...accepted].slice(0, maxFiles));
    }

    async function read() {
        setReading(true);
        setError(null);

        try {
            const data = new FormData();
            for (const file of await Promise.all(files.map(shrink))) {
                data.append("files[]", file);
            }

            const { data: result } = await axios.post(readUrl, data);
            onRead(result.sections, hasMenu ? mode : "replace");
            setFiles([]);
        } catch (e) {
            const errors = e.response?.data?.errors;
            setError(
                (errors && Object.values(errors)[0]?.[0]) ??
                    e.response?.data?.message ??
                    "Couldn't reach the server. Check your connection and try again.",
            );
        } finally {
            setReading(false);
        }
    }

    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={(e) => {
                e.preventDefault();
                setDragging(false);
                addFiles(e.dataTransfer.files);
            }}
            className={`relative rounded-2xl border-2 border-dashed transition-colors ${
                dragging ? "border-brand-accent bg-brand-accent/5" : "border-brand-border bg-brand-card"
            } ${hero ? "px-6 py-12 text-center" : "p-4"}`}
        >
            <input
                ref={inputRef}
                type="file"
                multiple
                accept={ACCEPTED.join(",")}
                className="hidden"
                onChange={(e) => {
                    addFiles(e.target.files);
                    e.target.value = "";
                }}
            />

            {onClose && !reading && (
                <button type="button" onClick={onClose} className={`${iconButton} absolute right-2 top-2`} aria-label="Close import">
                    <LuX className="h-4 w-4" />
                </button>
            )}

            {files.length === 0 ? (
                <div className={hero ? "" : "flex flex-wrap items-center gap-3 pr-8"}>
                    <span
                        className={`flex items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent ${
                            hero ? "mx-auto h-14 w-14" : "h-10 w-10 shrink-0"
                        }`}
                    >
                        <LuImageUp className={hero ? "h-7 w-7" : "h-5 w-5"} />
                    </span>
                    <div className={hero ? "mt-4" : "min-w-0 flex-1"}>
                        <p className={`font-heading font-semibold text-brand-text ${hero ? "text-xl" : "text-sm"}`}>
                            {hero ? "Upload the printed menu" : "Import from a photo"}
                        </p>
                        <p className="mt-0.5 text-sm text-brand-muted">
                            Drop photos or a PDF here · up to {maxFiles} pages · AI fills in the items
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className={hero ? `${primaryButton} mt-5` : secondaryButton}
                    >
                        Choose files
                    </button>
                </div>
            ) : (
                <div className={`flex flex-col ${hero ? "items-center" : "items-start pr-8"}`}>
                    {/* The pages as thumbnails, in reading order. */}
                    <ul className={`flex flex-wrap gap-3 ${hero ? "justify-center" : ""}`}>
                        {files.map((file, index) => (
                            <li key={`${file.name}-${index}`} className="relative">
                                <FileThumb file={file} />
                                <span className="absolute bottom-1 left-1 rounded-md bg-black/60 px-1.5 text-[11px] font-semibold text-white">
                                    {index + 1}
                                </span>
                                {!reading && (
                                    <button
                                        type="button"
                                        onClick={() => setFiles(files.filter((_, i) => i !== index))}
                                        className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full border border-brand-border bg-brand-card text-brand-muted hover:text-brand-text"
                                        aria-label={`Remove ${file.name}`}
                                    >
                                        <LuX className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </li>
                        ))}
                        {files.length < maxFiles && !reading && (
                            <li>
                                <button
                                    type="button"
                                    onClick={() => inputRef.current?.click()}
                                    className="flex h-28 w-20 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-brand-border text-xs text-brand-muted transition-colors hover:border-brand-accent hover:text-brand-text"
                                >
                                    <LuPlus className="h-4 w-4" /> Page
                                </button>
                            </li>
                        )}
                    </ul>

                    {hasMenu && (
                        <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-brand-text">
                            {[
                                ["replace", "Replace the current menu"],
                                ["append", "Add to the end"],
                            ].map(([value, text]) => (
                                <label key={value} className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="radio"
                                        name="read-mode"
                                        checked={mode === value}
                                        onChange={() => setMode(value)}
                                        className="h-4 w-4 accent-[var(--color-brand-accent)]"
                                    />
                                    {text}
                                </label>
                            ))}
                        </div>
                    )}

                    <button type="button" disabled={reading} onClick={read} className={`${primaryButton} mt-4`}>
                        {reading ? (
                            <>
                                <LuLoaderCircle className="h-4 w-4 animate-spin" /> Reading the menu…
                            </>
                        ) : (
                            <>
                                <LuSparkles className="h-4 w-4" /> Read {files.length === 1 ? "menu" : `${files.length} pages`}
                            </>
                        )}
                    </button>
                    {reading && <p className="mt-2 text-xs text-brand-muted">Can take up to a minute.</p>}
                    <FieldError message={error} />
                </div>
            )}

            {files.length === 0 && <FieldError message={error} />}
        </div>
    );
}

/** A chosen page: the photo itself, or a PDF icon. */
function FileThumb({ file }) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!file.type.startsWith("image/")) return;
        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return (
        <span className="flex h-28 w-20 items-center justify-center overflow-hidden rounded-xl border border-brand-border bg-brand-bg" title={file.name}>
            {url ? (
                <img src={url} alt={file.name} className="h-full w-full object-cover" />
            ) : (
                <span className="flex flex-col items-center gap-1 px-1 text-center">
                    {file.type === "application/pdf" ? <LuFileText className="h-6 w-6 text-brand-muted" /> : <LuImage className="h-6 w-6 text-brand-muted" />}
                    <span className="w-full truncate text-[10px] text-brand-muted">{file.name}</span>
                </span>
            )}
        </span>
    );
}

/* ---------- Choose theme ---------- */

/** Loads a theme's fonts so its "Aa" sample shows in its own type. */
function FontLoader({ theme }) {
    useThemeFonts(theme);
    return null;
}

/**
 * Themes listed on the left, the menu in the highlighted one on the right
 * (a full phone preview with the shop's own items). Nothing changes until
 * "Use this theme".
 */
function ThemePicker({ base, shop, sections, themes, current, onClose }) {
    const [picked, setPicked] = useState(current);
    const [saving, setSaving] = useState(false);
    const theme = themes.find((t) => t.key === picked) ?? themes[0];
    const preview = sections.some((s) => s.items.length > 0) ? sections : SAMPLE;

    useEffect(() => {
        const onKey = (e) => e.key === "Escape" && onClose();
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [onClose]);

    function use() {
        if (picked === current) return onClose();
        setSaving(true);
        router.put(
            `${base}/theme`,
            { theme: picked },
            { preserveScroll: true, preserveState: true, onSuccess: onClose, onFinish: () => setSaving(false) },
        );
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 sm:items-center sm:p-4" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Choose a menu theme"
                onClick={(e) => e.stopPropagation()}
                className="flex h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl border border-brand-border bg-brand-card sm:h-[min(820px,92vh)] sm:max-w-4xl sm:rounded-2xl"
            >
                <div className="flex items-center justify-between gap-3 border-b border-brand-border px-5 py-3.5">
                    <h2 className="font-heading text-lg font-semibold text-brand-text">Menu theme</h2>
                    <button type="button" onClick={onClose} className={iconButton} aria-label="Close">
                        <LuX className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex min-h-0 flex-1 flex-col sm:flex-row">
                    {/* Phones: a swipeable row. Larger: a list down the side. */}
                    <ul
                        role="listbox"
                        aria-label="Menu themes"
                        className="flex shrink-0 gap-1 overflow-x-auto border-b border-brand-border p-2 [scrollbar-width:none] sm:w-64 sm:[scrollbar-width:thin] sm:flex-col sm:overflow-y-auto sm:border-b-0 sm:border-r"
                    >
                        {themes.map((t) => (
                            <li key={t.key} role="option" aria-selected={t.key === picked} className="shrink-0">
                                <FontLoader theme={t} />
                                <button
                                    type="button"
                                    onClick={() => setPicked(t.key)}
                                    className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors ${
                                        t.key === picked ? "bg-brand-accent/10" : "hover:bg-brand-bg"
                                    }`}
                                >
                                    <span
                                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-border text-sm font-bold"
                                        style={{ background: t.page_bg, color: t.accent, fontFamily: t.heading_font }}
                                        aria-hidden="true"
                                    >
                                        Aa
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold text-brand-text">{t.name}</span>
                                        <span className="block text-xs text-brand-muted">{LAYOUT_LABELS[t.layout]}</span>
                                    </span>
                                    {t.key === current && <LuCheck className="h-4 w-4 shrink-0 text-brand-accent" aria-label="Current theme" />}
                                </button>
                            </li>
                        ))}
                    </ul>

                    <div className="flex min-h-0 flex-1 flex-col bg-brand-bg">
                        <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                            <div className="mx-auto h-full max-h-[620px] w-full max-w-[320px] overflow-y-auto overflow-x-hidden rounded-[2rem] border-[6px] border-neutral-900 [scrollbar-width:none]">
                                <MenuView shop={shop} sections={preview} theme={theme} embedded />
                            </div>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-t border-brand-border bg-brand-card px-5 py-3">
                            <p className="text-sm text-brand-muted">
                                {picked === current ? "Current theme" : `${theme.name} · ${LAYOUT_LABELS[theme.layout]} layout`}
                            </p>
                            <button type="button" disabled={saving || picked === current} onClick={use} className={primaryButton}>
                                {saving ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuCheck className="h-4 w-4" />}
                                Use this theme
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

/** Three dots of a theme's colours, for the "Choose theme" button. */
function Swatch({ theme }) {
    return (
        <span className="flex -space-x-1" aria-hidden="true">
            {[theme.page_bg, theme.deep, theme.accent].map((color, i) => (
                <span key={i} className="h-4 w-4 rounded-full ring-2 ring-brand-card" style={{ background: color }} />
            ))}
        </span>
    );
}

/* ---------- Editor ---------- */

/**
 * Free-text tags: the item's tags as chips (× removes), "+ Tag" opens a small
 * input - Enter or comma adds what's typed - with this menu's own tags
 * offered as one-tap suggestions.
 */
function TagEditor({ tags, suggestions, onChange }) {
    const [open, setOpen] = useState(false);
    const [text, setText] = useState("");
    const full = tags.length >= MAX_TAGS;
    const query = text.trim().toLowerCase();
    const offered = suggestions.filter((t) => !hasTag(tags, t) && t.toLowerCase().includes(query)).slice(0, 8);

    function add(raw) {
        const tag = tidyTag(raw);
        if (tag && !full && !hasTag(tags, tag)) onChange([...tags, tag]);
        setText("");
    }

    function close() {
        if (text.trim()) add(text);
        setOpen(false);
        setText("");
    }

    return (
        <div className="px-1.5 pt-1">
            <div className="flex flex-wrap items-center gap-1">
                {tags.map((tag) => (
                    <span key={tag} className="inline-flex items-center gap-0.5 rounded-full bg-brand-accent/15 py-0.5 pl-2 pr-1 text-[11px] font-medium text-brand-text">
                        {tag}
                        <button
                            type="button"
                            onClick={() => onChange(tags.filter((t) => t !== tag))}
                            className="rounded-full p-0.5 text-brand-muted hover:text-brand-text"
                            aria-label={`Remove tag ${tag}`}
                        >
                            <LuX className="h-3 w-3" />
                        </button>
                    </span>
                ))}

                {open ? (
                    <input
                        type="text"
                        autoFocus
                        value={text}
                        maxLength={TAG_LENGTH}
                        onChange={(e) => (e.target.value.includes(",") ? add(e.target.value.replace(/,/g, "")) : setText(e.target.value))}
                        onKeyDown={(e) => {
                            if (e.key === "Enter") {
                                e.preventDefault();
                                add(text);
                            } else if (e.key === "Escape") {
                                setOpen(false);
                                setText("");
                            } else if (e.key === "Backspace" && !text && tags.length) {
                                onChange(tags.slice(0, -1));
                            }
                        }}
                        onBlur={close}
                        placeholder={full ? `Up to ${MAX_TAGS} tags` : "e.g. Vegan, then Enter"}
                        disabled={full}
                        aria-label="New tag"
                        className="w-36 rounded-full border border-brand-accent bg-brand-card px-2.5 py-0.5 text-[11px] text-brand-text outline-none"
                    />
                ) : (
                    !full && (
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            title="Small labels shown on the item in the menu, e.g. Vegan, Halal, Spicy, New"
                            className="flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[11px] font-medium text-brand-muted hover:text-brand-text"
                        >
                            <LuTag className="h-3 w-3" /> {tags.length ? "Tag" : "Add tag · Vegan, Spicy…"}
                        </button>
                    )
                )}
            </div>

            {open && !full && offered.length > 0 && (
                <div className="mt-1.5 flex flex-wrap gap-1">
                    {offered.map((tag) => (
                        <button
                            key={tag}
                            type="button"
                            // mousedown so it lands before the input's blur closes the list
                            onMouseDown={(e) => {
                                e.preventDefault();
                                add(tag);
                            }}
                            className="rounded-full px-2 py-0.5 text-[11px] font-medium text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                        >
                            + {tag}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * An item's photo: the thumbnail (click it to look for one), and under the
 * tags why there's none, "Find photo" / "Change photo" (with other search
 * words if you like) and "Remove photo". Finding needs a saved item (an id);
 * taking a photo off is an unsaved change.
 */
function PhotoThumb({ item, searching, canFind, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={!canFind}
            title={item.id ? (item.image_url ? "Change photo" : "Find a photo") : "Save the menu first"}
            className="mt-1 flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-brand-border bg-brand-bg transition hover:border-brand-accent disabled:cursor-default disabled:hover:border-brand-border"
        >
            {item.image_url ? (
                <img src={item.image_url} alt="" className="h-full w-full object-cover" />
            ) : searching ? (
                <LuLoaderCircle className="h-4 w-4 animate-spin text-brand-muted" aria-label="Looking for a photo" />
            ) : (
                <LuImagePlus className="h-4 w-4 text-brand-muted/70" aria-hidden="true" />
            )}
        </button>
    );
}

function PhotoActions({ item, asking, setAsking, canFind, onFind, onRemovePhoto }) {
    const [query, setQuery] = useState(item.name);
    const note = { not_found: "No photo found", rejected: "No matching photo", removed: "Photo taken off" }[item.image_status];
    const link = "font-medium text-brand-accent hover:underline disabled:cursor-not-allowed disabled:text-brand-muted disabled:no-underline";

    function find() {
        setAsking(false);
        onFind(query.trim() || item.name);
    }

    if (asking) {
        return (
            <div className="flex items-center gap-1.5 px-2 pt-1.5">
                <input
                    type="text"
                    autoFocus
                    value={query}
                    maxLength={120}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === "Enter") {
                            e.preventDefault();
                            find();
                        } else if (e.key === "Escape") setAsking(false);
                    }}
                    aria-label="Search words for the photo"
                    className="w-48 rounded-lg border border-brand-accent bg-brand-card px-2 py-1 text-xs text-brand-text outline-none"
                />
                <button type="button" onClick={find} className={`text-xs ${link}`}>
                    Find
                </button>
                <button type="button" onClick={() => setAsking(false)} className="text-xs text-brand-muted hover:text-brand-text">
                    Cancel
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 px-2 pt-1.5 text-xs">
            {note && (
                <span className="text-brand-muted" title={item.image_reason ?? undefined}>
                    {note}
                </span>
            )}
            <button type="button" disabled={!canFind} onClick={() => setAsking(true)} title={item.id ? undefined : "Save the menu first"} className={link}>
                {item.image_url ? "Change photo" : "Find photo"}
            </button>
            {item.image_url && (
                <button type="button" onClick={onRemovePhoto} className="font-medium text-brand-muted hover:text-red-600">
                    Remove photo
                </button>
            )}
        </div>
    );
}

/** Checkbox for picking items to look up photos for. `partial` = some of a section. */
function Tick({ checked, partial = false, onChange, label, className = "" }) {
    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={partial ? "mixed" : checked}
            aria-label={label}
            onClick={() => onChange(!checked)}
            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-colors ${
                checked || partial ? "border-brand-accent bg-brand-accent text-brand-accent-text" : "border-brand-border bg-brand-card hover:border-brand-accent"
            } ${className}`}
        >
            {checked ? <LuCheck className="h-3 w-3" strokeWidth={3} /> : partial ? <LuMinus className="h-3 w-3" strokeWidth={3} /> : null}
        </button>
    );
}

function ItemRow({ item, path, errors, first, last, suggestions, photos, onChange, onMove, onRemove }) {
    const [asking, setAsking] = useState(false);
    const canFind = Boolean(item.id) && !photos.busy;
    const selecting = photos.selected !== null;

    return (
        <li className={`group flex items-start gap-1 px-2 py-2 ${selecting && photos.selected.has(item.id) ? "bg-brand-accent/5" : ""}`}>
            {selecting &&
                (item.id ? (
                    <Tick
                        checked={photos.selected.has(item.id)}
                        onChange={(on) => photos.toggleSelected([item.id], on)}
                        label={`Select ${item.name}`}
                        className="mx-1 mt-[18px]"
                    />
                ) : (
                    <span className="mx-1 w-5 shrink-0" />
                ))}
            {photos.enabled && <PhotoThumb item={item} searching={photos.searching(item)} canFind={canFind} onClick={() => setAsking(true)} />}
            <div className="min-w-0 flex-1">
                <div className="flex gap-1">
                    <input
                        type="text"
                        value={item.name}
                        onChange={(e) => onChange({ name: e.target.value })}
                        placeholder="Item name"
                        aria-label="Item name"
                        className={`${quietInput} flex-1 font-medium ${errors[`${path}.name`] ? errorRing : ""}`}
                    />
                    <input
                        type="text"
                        value={item.price}
                        onChange={(e) => onChange({ price: e.target.value })}
                        placeholder="£0.00"
                        aria-label="Price"
                        className={`${quietInput} w-28 shrink-0 text-right font-semibold tabular-nums ${errors[`${path}.price`] ? errorRing : ""}`}
                    />
                </div>
                <input
                    type="text"
                    value={item.description}
                    onChange={(e) => onChange({ description: e.target.value })}
                    placeholder="Add a description"
                    aria-label="Description"
                    className={`${quietInput} w-full !py-1 text-[13px] text-brand-muted ${errors[`${path}.description`] ? errorRing : ""}`}
                />
                <TagEditor tags={item.tags} suggestions={suggestions} onChange={(tags) => onChange({ tags })} />
                {photos.enabled && (
                    <PhotoActions
                        item={item}
                        asking={asking}
                        setAsking={setAsking}
                        canFind={canFind}
                        onFind={(query) => photos.findAgain(item, query)}
                        onRemovePhoto={() => onChange({ image_path: null, image_url: null, image_status: "removed", image_reason: null })}
                    />
                )}
                <FieldError
                    message={
                        errors[`${path}.name`] ??
                        errors[`${path}.price`] ??
                        errors[`${path}.description`] ??
                        errors[`${path}.tags`] ??
                        Object.entries(errors).find(([k]) => k.startsWith(`${path}.tags.`))?.[1]
                    }
                />
            </div>
            <div className="flex shrink-0 items-center opacity-100 transition-opacity focus-within:opacity-100 sm:opacity-0 sm:group-hover:opacity-100">
                <button type="button" disabled={first} onClick={() => onMove(-1)} className={iconButton} aria-label="Move item up">
                    <LuArrowUp className="h-4 w-4" />
                </button>
                <button type="button" disabled={last} onClick={() => onMove(1)} className={iconButton} aria-label="Move item down">
                    <LuArrowDown className="h-4 w-4" />
                </button>
                <button type="button" onClick={onRemove} className={`${iconButton} hover:!text-red-600`} aria-label="Remove item">
                    <LuTrash2 className="h-4 w-4" />
                </button>
            </div>
        </li>
    );
}

function SectionBlock({ section, si, count, errors, suggestions, photos, onChange, onMove, onRemove }) {
    const [open, setOpen] = useState(true);
    const updateItem = (ii, patch) => onChange({ items: section.items.map((item, i) => (i === ii ? { ...item, ...patch } : item)) });
    const ids = section.items.filter((i) => i.id).map((i) => i.id);
    const picked = photos.selected ? ids.filter((id) => photos.selected.has(id)).length : 0;

    return (
        <section className="rounded-2xl border border-brand-border bg-brand-card">
            <div className="flex items-center gap-1 px-2 py-2">
                {photos.selected !== null && ids.length > 0 && (
                    <Tick
                        checked={picked === ids.length}
                        partial={picked > 0 && picked < ids.length}
                        onChange={(on) => photos.toggleSelected(ids, on)}
                        label={`Select all in ${section.name || "this section"}`}
                        className="mx-1"
                    />
                )}
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className={iconButton}
                    aria-expanded={open}
                    aria-label={open ? "Collapse section" : "Expand section"}
                >
                    <LuChevronDown className={`h-4 w-4 transition-transform ${open ? "" : "-rotate-90"}`} />
                </button>
                <input
                    type="text"
                    value={section.name}
                    onChange={(e) => onChange({ name: e.target.value })}
                    placeholder="Section name, e.g. Hot drinks"
                    aria-label="Section name"
                    className={`${quietInput} flex-1 font-heading !text-base font-semibold ${errors[`sections.${si}.name`] ? errorRing : ""}`}
                />
                <span className="shrink-0 px-1 text-xs tabular-nums text-brand-muted">{section.items.length}</span>
                <button type="button" disabled={si === 0} onClick={() => onMove(-1)} className={iconButton} aria-label="Move section up">
                    <LuArrowUp className="h-4 w-4" />
                </button>
                <button type="button" disabled={si === count - 1} onClick={() => onMove(1)} className={iconButton} aria-label="Move section down">
                    <LuArrowDown className="h-4 w-4" />
                </button>
                <button type="button" onClick={onRemove} className={`${iconButton} hover:!text-red-600`} aria-label="Remove section">
                    <LuTrash2 className="h-4 w-4" />
                </button>
            </div>
            <div className="px-3">
                <FieldError message={errors[`sections.${si}.name`]} />
            </div>

            {open && (
                <>
                    <ul className="divide-y divide-brand-border border-t border-brand-border">
                        {section.items.map((item, ii) => (
                            <ItemRow
                                key={item.key}
                                item={item}
                                path={`sections.${si}.items.${ii}`}
                                errors={errors}
                                suggestions={suggestions}
                                photos={photos}
                                first={ii === 0}
                                last={ii === section.items.length - 1}
                                onChange={(patch) => updateItem(ii, patch)}
                                onMove={(by) => onChange({ items: move(section.items, ii, by) })}
                                onRemove={() => onChange({ items: section.items.filter((_, i) => i !== ii) })}
                            />
                        ))}
                    </ul>
                    <div className="border-t border-brand-border px-2 py-1.5">
                        <button
                            type="button"
                            onClick={() => onChange({ items: [...section.items, blankItem()] })}
                            className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-accent hover:bg-brand-bg"
                        >
                            <LuPlus className="h-4 w-4" /> Add item
                        </button>
                    </div>
                </>
            )}
        </section>
    );
}

/**
 * Unsaved edits kept in sessionStorage, so a full reload (a new build makes
 * Inertia reload the page on the next request) doesn't lose an imported menu.
 */
const draftKey = (shopId) => `menu-draft-${shopId}`;

function readDraft(shopId) {
    try {
        const draft = JSON.parse(sessionStorage.getItem(draftKey(shopId)));
        return Array.isArray(draft) ? draft : null;
    } catch {
        return null;
    }
}

function writeDraft(shopId, sections) {
    try {
        if (sections) sessionStorage.setItem(draftKey(shopId), JSON.stringify(withoutKeys(sections)));
        else sessionStorage.removeItem(draftKey(shopId));
    } catch {
        // storage blocked - edits just aren't kept across a reload
    }
}

/**
 * The menu editor, shared by the admin (any shop, Admin/Menu/Edit) and the
 * owner (their own shop, Dashboard/Menu). `layout` is the page shell;
 * `urls.base` is where the menu is saved (+ /read, /theme), `urls.back` the
 * optional "Back to shop" link.
 */
export default function MenuEditor({ layout: Layout, isAdmin = false, shop, sections: saved, menuUrl, urls, aiEnabled, imagesEnabled = false, maxFiles, themes, currentTheme }) {
    const [draft] = useState(() => readDraft(shop.id));
    const [sections, setSections] = useState(() => withKeys(draft ?? saved));
    const [dirty, setDirty] = useState(draft !== null);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(draft ? "Your unsaved changes were restored - save them or discard." : null);

    useEffect(() => writeDraft(shop.id, dirty ? sections : null), [shop.id, dirty, sections]);
    const [importOpen, setImportOpen] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [confirm, confirmDialog] = useConfirm();

    const theme = themes.find((t) => t.key === currentTheme) ?? themes[0];
    const itemCount = sections.reduce((sum, s) => sum + s.items.length, 0);
    // This menu's own tags first (most used), so a shop's labels stay consistent.
    const suggestions = useMemo(() => tagSuggestions(sections), [sections]);
    const savedHasItems = saved.some((s) => s.items.length > 0);
    const empty = sections.length === 0;

    // ---- Photos: only when asked (Find photos / an item's search), a few items per request (no queue on the host) ----
    // The PhotoFinder panel's state: {total, index, current, outcome, results, finished, note}.
    const [finder, setFinder] = useState(null);
    const running = useRef(false);
    const stopRequested = useRef(false);
    const finderRunning = finder !== null && !finder.finished;

    /**
     * Stop after the item in flight: its request is already running on the
     * server and may save a photo, so it's let finish rather than cut off.
     */
    function stopFinder() {
        stopRequested.current = true;
        setFinder((f) => (f && !f.finished ? { ...f, stopping: true } : f));
    }

    /**
     * One request per item (so the panel knows which one is being checked),
     * holding each outcome on screen for a moment before the next.
     */
    async function runFinder(queue, bodyFor, { untried = false } = {}) {
        running.current = true;
        stopRequested.current = false;
        let total = queue.length;
        let note = null;
        const results = [];
        setFinder({ total, index: 0, current: null, outcome: null, results, finished: false, stopping: false, note });
        try {
            for (let i = 0; i < total && queue[i]; i++) {
                if (stopRequested.current) {
                    note = `Stopped - ${total - i} ${total - i === 1 ? "item" : "items"} not checked.`;
                    break;
                }
                setFinder((f) => ({ ...f, total, index: i, outcome: null, current: { ...queue[i], startedAt: Date.now() } }));
                const { data } = await axios.post(`${urls.base}/images`, bodyFor(i));
                setSections((current) => withPhotos(current, data.items));
                if (data.unavailable) {
                    note = "Photo checks are busy right now - try again in a minute for the rest.";
                    break;
                }
                const r = data.items[0];
                if (!r) break;
                const outcome = { name: r.name, section: r.section, status: r.outcome, image_url: r.image_url, reason: r.outcome_reason };
                results.push(outcome);
                // Untried runs: the server knows best how many are left.
                if (untried) total = Math.min(queue.length, i + 1 + data.remaining);
                setFinder((f) => ({ ...f, total, outcome, results: [...results] }));
                if (!stopRequested.current) await new Promise((resolve) => setTimeout(resolve, 1100));
            }
        } catch {
            note = "Couldn't look for photos right now - try again in a minute.";
        } finally {
            running.current = false;
            setFinder((f) => ({ ...f, finished: true, note }));
        }
    }

    function findPhotos({ retry = false } = {}) {
        if (!imagesEnabled || running.current) return;
        // Same order the server works in: saved menu order, untried first (or, with
        // `retry`, also the ones that got no photo last time).
        const queue = sections.flatMap((s) =>
            s.items
                .filter((i) => i.id && (i.image_status === null || (retry && ["not_found", "rejected"].includes(i.image_status))))
                .map((i) => ({ name: i.name, section: s.name })),
        );
        if (queue.length) {
            runFinder(queue, (i) => ({ limit: 1, ...(retry && i === 0 && { retry: true }) }), { untried: true });
            return;
        }
        // Nothing to look for: say so, rather than leaving the last run's panel up.
        setFinder({
            total: 0,
            index: 0,
            current: null,
            outcome: null,
            results: [],
            finished: true,
            stopping: false,
            note: "Every item already has a photo or was taken off by hand. Use Select to look again for particular items (a better photo replaces the current one).",
        });
    }

    function findAgain(item, query) {
        if (running.current) return;
        const section = sections.find((s) => s.items.includes(item))?.name;
        runFinder([{ name: item.name, section }], () => ({ item: item.id, query }));
    }

    // Pick items for a bulk search (ids of saved items).
    const [selected, setSelected] = useState(null); // null = not selecting, else a Set
    const savedItems = sections.flatMap((s) => s.items.filter((i) => i.id).map((i) => ({ ...i, section: s.name })));
    const untriedCount = savedItems.filter((i) => i.image_status === null).length;

    function toggleSelected(ids, on) {
        setSelected((current) => {
            const next = new Set(current);
            ids.forEach((id) => (on ? next.add(id) : next.delete(id)));
            return next;
        });
    }

    function findSelected() {
        if (running.current || !selected?.size) return;
        const queue = savedItems.filter((i) => selected.has(i.id));
        setSelected(null);
        runFinder(queue, (i) => ({ item: queue[i].id }));
    }

    const photos = {
        enabled: imagesEnabled,
        busy: finderRunning,
        searching: (item) => finderRunning && !finder.outcome && finder.current?.name === item.name,
        findAgain,
        selected,
        toggleSelected,
    };

    function change(next) {
        setSections(next);
        setDirty(true);
    }

    function updateSection(si, patch) {
        change(sections.map((s, i) => (i === si ? { ...s, ...patch } : s)));
    }

    function addSection() {
        change([...sections, { key: key(), name: "", items: [blankItem()] }]);
    }

    function onRead(read, mode) {
        change(mode === "append" ? [...sections, ...withKeys(read)] : withKeys(read));
        setErrors({});
        setImportOpen(false);
        const items = read.reduce((sum, s) => sum + s.items.length, 0);
        setNotice(`Read ${items} items in ${read.length} sections - check them, then save.`);
    }

    function save() {
        setSaving(true);
        router.put(
            urls.base,
            { sections: withoutKeys(sections) },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    // Saving re-creates every item: take the fresh ids / photos from the server.
                    setSections(withKeys(page.props.sections));
                    setDirty(false);
                    setErrors({});
                    setNotice(null);
                },
                onError: setErrors,
                onFinish: () => setSaving(false),
            },
        );
    }

    async function discard() {
        const ok = await confirm({ title: "Discard changes?", message: "The menu goes back to how it was last saved.", confirmLabel: "Discard" });
        if (!ok) return;
        setSections(withKeys(saved));
        setDirty(false);
        setErrors({});
        setNotice(null);
    }

    async function clearMenu() {
        const ok = await confirm({
            title: "Clear the menu?",
            message: `Every section and item of ${shop.name}'s menu is deleted.`,
            confirmLabel: "Clear menu",
            danger: true,
        });
        if (!ok) return;

        router.delete(urls.base, {
            preserveScroll: true,
            onSuccess: () => {
                setSections([]);
                setDirty(false);
            },
        });
    }

    async function removeSection(si) {
        const section = sections[si];
        if (section.items.some((item) => item.name.trim())) {
            const ok = await confirm({
                title: "Remove this section?",
                message: `“${section.name || "Untitled"}” and its ${section.items.length} items.`,
                confirmLabel: "Remove",
                danger: true,
            });
            if (!ok) return;
        }
        change(sections.filter((_, i) => i !== si));
    }

    return (
        <Layout
            shop={shop}
            title="Menu"
            description={shop.name}
            actions={
                <div className="flex flex-wrap gap-2">
                    {urls.back && (
                        <Link href={urls.back} className={secondaryButton}>
                            Back to shop
                        </Link>
                    )}
                    <button type="button" onClick={() => setPickerOpen(true)} className={secondaryButton}>
                        <LuPalette className="h-4 w-4" /> Theme: {theme.name} <Swatch theme={theme} />
                    </button>
                    {savedHasItems && (
                        <a href={menuUrl} target="_blank" rel="noreferrer" className={secondaryButton}>
                            <LuExternalLink className="h-4 w-4" /> Open menu
                        </a>
                    )}
                </div>
            }
        >
            {confirmDialog}
            {pickerOpen && (
                <ThemePicker
                    base={urls.base}
                    shop={shop}
                    sections={withoutKeys(sections)}
                    themes={themes}
                    current={theme.key}
                    onClose={() => setPickerOpen(false)}
                />
            )}

            {empty ? (
                <div className="mx-auto max-w-2xl">
                    {aiEnabled ? (
                        <ImportBox readUrl={`${urls.base}/read`} maxFiles={maxFiles} hasMenu={false} hero onRead={onRead} />
                    ) : (
                        // Only the admin can do anything about a missing key.
                        isAdmin && (
                            <p className="rounded-2xl border border-brand-border bg-brand-card px-5 py-4 text-sm text-brand-muted">
                                Add <code className="font-mono">GEMINI_API_KEY</code> to .env to read menus from photos.
                            </p>
                        )
                    )}
                    <p className="mt-4 text-center text-sm text-brand-muted">
                        {aiEnabled && "or "}
                        <button type="button" onClick={addSection} className="font-semibold text-brand-accent hover:underline">
                            type it in yourself
                        </button>
                    </p>
                </div>
            ) : (
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="min-w-0 space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="mr-auto text-sm text-brand-muted">
                                {sections.length} {sections.length === 1 ? "section" : "sections"} · {itemCount} {itemCount === 1 ? "item" : "items"} · click any text to edit
                            </p>
                            {aiEnabled && !importOpen && (
                                <button type="button" onClick={() => setImportOpen(true)} className={secondaryButton}>
                                    <LuSparkles className="h-4 w-4" /> Import from photo
                                </button>
                            )}
                            {imagesEnabled && savedHasItems && (
                                <button
                                    type="button"
                                    onClick={() => findPhotos({ retry: !needsPhoto(sections) })}
                                    disabled={photos.busy || dirty}
                                    title={dirty ? "Save your changes first" : "Look up photos for items that don't have one"}
                                    className={secondaryButton}
                                >
                                    {finderRunning ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuImagePlus className="h-4 w-4" />} Find photos
                                    {/* After a stopped run: it carries on with the ones not checked yet. */}
                                    {untriedCount > 0 && untriedCount < savedItems.length && (
                                        <span className="text-xs font-normal text-brand-muted">· {untriedCount} left</span>
                                    )}
                                </button>
                            )}
                            {imagesEnabled && savedHasItems && (
                                <button
                                    type="button"
                                    onClick={() => setSelected(selected ? null : new Set())}
                                    disabled={finderRunning || dirty}
                                    title={dirty ? "Save your changes first" : "Pick items to look up photos for"}
                                    aria-pressed={selected !== null}
                                    className={`${secondaryButton} ${selected ? "!border-brand-accent !text-brand-accent" : ""}`}
                                >
                                    <LuListChecks className="h-4 w-4" /> Select
                                </button>
                            )}
                            <button type="button" onClick={addSection} className={secondaryButton}>
                                <LuPlus className="h-4 w-4" /> Section
                            </button>
                        </div>

                        {importOpen && (
                            <ImportBox
                                readUrl={`${urls.base}/read`}
                                maxFiles={maxFiles}
                                hasMenu
                                onRead={onRead}
                                onClose={() => setImportOpen(false)}
                            />
                        )}

                        {notice && (
                            <p className="flex items-start gap-2 rounded-xl bg-brand-accent/10 px-4 py-3 text-sm text-brand-text">
                                <LuSparkles className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" /> {notice}
                            </p>
                        )}
                        {selected && (
                            <div className="sticky top-2 z-10 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-2xl border border-brand-accent/40 bg-brand-card px-3 py-2">
                                <Tick
                                    checked={selected.size > 0 && selected.size === savedItems.length}
                                    partial={selected.size > 0 && selected.size < savedItems.length}
                                    onChange={(on) => toggleSelected(savedItems.map((i) => i.id), on)}
                                    label="Select every item"
                                />
                                <span className="text-sm font-medium tabular-nums text-brand-text">{selected.size} selected</span>
                                <button
                                    type="button"
                                    onClick={() => toggleSelected(savedItems.filter((i) => !i.image_url).map((i) => i.id), true)}
                                    className="text-sm font-medium text-brand-accent hover:underline"
                                >
                                    + Without a photo
                                </button>
                                <span className="ml-auto flex items-center gap-2">
                                    <button type="button" onClick={() => setSelected(null)} className={secondaryButton}>
                                        Cancel
                                    </button>
                                    <button type="button" onClick={findSelected} disabled={!selected.size || dirty} className={primaryButton}>
                                        <LuImagePlus className="h-4 w-4" /> Find photos{selected.size > 0 && ` (${selected.size})`}
                                    </button>
                                </span>
                            </div>
                        )}
                        {finder && <PhotoFinder run={finder} onStop={stopFinder} onClose={() => setFinder(null)} />}

                        {sections.map((section, si) => (
                            <SectionBlock
                                key={section.key}
                                section={section}
                                si={si}
                                count={sections.length}
                                errors={errors}
                                suggestions={suggestions}
                                photos={photos}
                                onChange={(patch) => updateSection(si, patch)}
                                onMove={(by) => change(move(sections, si, by))}
                                onRemove={() => removeSection(si)}
                            />
                        ))}

                        <button
                            type="button"
                            onClick={addSection}
                            className="flex w-full items-center justify-center gap-1.5 rounded-2xl border border-dashed border-brand-border py-3 text-sm font-medium text-brand-muted transition-colors hover:border-brand-accent hover:text-brand-text"
                        >
                            <LuPlus className="h-4 w-4" /> Add section
                        </button>

                        {savedHasItems && (
                            <div className="pt-2">
                                <button type="button" onClick={clearMenu} className="text-sm font-medium text-red-600 hover:underline">
                                    Clear the whole menu
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Live: what customers see, in the chosen theme, including unsaved edits. */}
                    <aside className="hidden lg:sticky lg:top-6 lg:block">
                        <div className="mb-2 flex items-center justify-between text-xs text-brand-muted">
                            <span>Preview{dirty && " · unsaved"}</span>
                            <button type="button" onClick={() => setPickerOpen(true)} className="font-semibold text-brand-accent hover:underline">
                                Change theme
                            </button>
                        </div>
                        <div className="h-[680px] overflow-y-auto overflow-x-hidden rounded-[2rem] border-[6px] border-neutral-900 [scrollbar-width:thin]">
                            <MenuView shop={shop} sections={withoutKeys(sections)} theme={theme} embedded />
                        </div>
                    </aside>
                </div>
            )}

            {(dirty || saving) && (
                <div className="sticky bottom-0 z-10 mt-4 flex flex-wrap items-center gap-3 border-t border-brand-border bg-brand-bg py-3">
                    <button type="button" disabled={saving} onClick={save} className={primaryButton}>
                        {saving ? "Saving…" : "Save menu"}
                    </button>
                    <button type="button" disabled={saving} onClick={discard} className={secondaryButton}>
                        Discard
                    </button>
                    <span className="text-sm text-brand-muted">Unsaved changes</span>
                </div>
            )}
        </Layout>
    );
}
