import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { LuCheck, LuImage, LuImagePlus, LuMoon, LuPaintbrush, LuPalette, LuRotateCcw, LuSparkles, LuStamp, LuStar, LuStore, LuSun, LuTrash2 } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { FieldError, inputClass, Panel, primaryButton, secondaryButton, Switch } from '@/Components/Dashboard/Ui';
import { SIGNUP_ICON_GROUPS, SignupIcon } from '@/lib/signupIcons';
import StampGrid from '@/Components/StampGrid';
import { STAMP_ICONS } from '@/lib/stampIcons';
import { CUSTOM_COLOR_FIELDS, fontNameOf, themeVars, useThemeFonts, withCustomisation } from '@/lib/theme';
import { HEADER_STYLE_DEFAULTS, headerTextStyle, headerTintStyle } from '@/lib/headerStyle';

const CATEGORY_LABELS = {
    cafe: 'Cafe',
    bakery: 'Bakery',
    barber: 'Barber',
    pub: 'Pub & bar',
    dessert: 'Dessert',
    retail: 'Retail',
    general: 'General',
};

const TABS = [
    { id: 'themes', label: 'Themes', icon: LuPalette },
    { id: 'customise', label: 'Customise', icon: LuPaintbrush },
    { id: 'stamp', label: 'Stamp icon', icon: LuStamp },
    { id: 'signup', label: 'Sign-up icon', icon: LuSparkles },
    { id: 'banner', label: 'Banner & logo', icon: LuImage },
];

/**
 * A small copy of the customer card page, built from the same brand-*
 * utilities - so setting the theme's variables on it shows exactly what
 * customers will see.
 */
function PhonePreview({ theme, shop, stampIcon, bannerUrl, logoUrl, headerStyle }) {
    const filled = Math.min(3, shop.max_stamps);

    return (
        <div
            style={themeVars(theme)}
            className="relative mx-auto w-full max-w-[300px] overflow-hidden rounded-[2rem] border-[6px] border-neutral-900 bg-brand-bg font-sans text-brand-text shadow-xl"
        >
            {/* Same header as the card page: photo under the owner's tint (or the deep colour), logo, name and reward. */}
            <div className="relative overflow-hidden bg-brand-deep">
                {bannerUrl && (
                    <>
                        <img src={bannerUrl} alt="" className="absolute inset-0 h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-black" style={headerTintStyle(headerStyle)} />
                    </>
                )}
                <div className="relative flex items-center gap-3 px-4 pb-7 pt-16">
                    <span className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white">
                        {logoUrl ? <img src={logoUrl} alt="" className="h-full w-full object-cover" /> : <LuStore className="h-6 w-6 text-brand-accent" />}
                    </span>
                    <div className="min-w-0" style={headerTextStyle(headerStyle)}>
                        <p className="truncate font-heading text-base font-bold leading-tight">{shop.name}</p>
                        <p className="truncate text-[11px] opacity-80">{shop.reward_title}</p>
                    </div>
                </div>
            </div>
            <div className="relative -mt-4 rounded-t-2xl bg-brand-bg px-4 pb-5 pt-1">
                {/* Same as the customer's stamp card: the count, then the real stamp grid. */}
                <div className="mt-3 rounded-brand border border-brand-border bg-brand-card p-3">
                    <p className="font-heading text-base font-bold tabular-nums">
                        {filled}
                        <span className="text-xs font-semibold text-brand-muted">/{shop.max_stamps} stamps</span>
                    </p>
                    <StampGrid className="mt-2" size="sm" animate={false} total={shop.max_stamps} stamps={filled} icon={stampIcon} />
                </div>

                <div className="mt-3 flex items-center gap-3 rounded-brand border border-brand-border bg-brand-card px-3 py-2.5 text-xs font-medium shadow-sm">
                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-accent/10 text-brand-accent">
                        <LuStar className="h-3.5 w-3.5" />
                    </span>
                    Rate your visit
                </div>

                <div className="mt-3 rounded-brand bg-brand-accent py-2.5 text-center text-xs font-semibold text-brand-accent-text">Show my QR code</div>
            </div>
        </div>
    );
}

/* ---------- Themes tab ---------- */

function ThemeOption({ theme, selected, current, customised, isDefault, onSelect }) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={`group overflow-hidden rounded-2xl border bg-brand-card text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${
                selected ? 'border-brand-accent ring-2 ring-brand-accent' : 'border-brand-border'
            }`}
        >
            {/* Swatch: page background, card with a filled stamp, accent + text colours. */}
            <div className="flex h-16" style={{ background: theme.page_bg }}>
                <div className="flex flex-1 items-center justify-center border-r" style={{ background: theme.card_bg, borderColor: theme.border }}>
                    <span className="h-6 w-6 rounded-full" style={{ background: theme.stamp_filled, border: `1px solid ${theme.border}` }} />
                </div>
                <div className="flex flex-1 items-center justify-center gap-1.5">
                    <span className="h-5 w-5 rounded-full" style={{ background: theme.accent }} />
                    <span className="h-5 w-5 rounded-full" style={{ background: theme.text, opacity: 0.85 }} />
                </div>
            </div>

            <div className="p-3.5">
                <div className="flex items-center justify-between gap-2">
                    <p className="truncate text-sm font-semibold text-brand-text">{theme.name}</p>
                    {!current && isDefault && (
                        <span className="shrink-0 rounded-full bg-brand-bg px-2 py-0.5 text-[10px] font-semibold text-brand-muted">Default</span>
                    )}
                    {current && (
                        <span className="flex shrink-0 items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">
                            <LuCheck className="h-3 w-3" /> {customised ? 'Current · customised' : 'Current'}
                        </span>
                    )}
                </div>
                <p className="mt-1 line-clamp-2 text-xs leading-snug text-brand-muted">{theme.blurb}</p>
                <p className="mt-2 flex items-center gap-1 text-[10px] uppercase tracking-wide text-brand-muted">
                    {CATEGORY_LABELS[theme.category] ?? theme.category}
                    <span aria-hidden="true">·</span>
                    {theme.mood === 'dark' ? <LuMoon className="h-3 w-3" /> : <LuSun className="h-3 w-3" />}
                    {theme.mood}
                </p>
            </div>
        </button>
    );
}

function Chip({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors ${
                active ? 'border-brand-accent bg-brand-accent text-brand-accent-text' : 'border-brand-border bg-brand-card text-brand-text hover:bg-brand-bg'
            }`}
        >
            {children}
        </button>
    );
}

function ThemesTab({ themes, selected, onSelect, currentTheme, customised, defaultTheme }) {
    const [category, setCategory] = useState('all');
    const [mood, setMood] = useState('all');

    const categories = useMemo(() => [...new Set(Object.values(themes).map((t) => t.category))], [themes]);
    const visible = Object.entries(themes).filter(
        ([, t]) => (category === 'all' || t.category === category) && (mood === 'all' || t.mood === mood),
    );

    return (
        <>
            <div className="space-y-3">
                <div className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0">
                    <Chip active={category === 'all'} onClick={() => setCategory('all')}>
                        All
                    </Chip>
                    {categories.map((c) => (
                        <Chip key={c} active={category === c} onClick={() => setCategory(c)}>
                            {CATEGORY_LABELS[c] ?? c}
                        </Chip>
                    ))}
                </div>
                <div className="flex gap-2">
                    <Chip active={mood === 'all'} onClick={() => setMood('all')}>
                        Light & dark
                    </Chip>
                    <Chip active={mood === 'light'} onClick={() => setMood('light')}>
                        Light
                    </Chip>
                    <Chip active={mood === 'dark'} onClick={() => setMood('dark')}>
                        Dark
                    </Chip>
                </div>
            </div>

            <p className="mt-4 text-xs text-brand-muted">
                {visible.length} {visible.length === 1 ? 'theme' : 'themes'}
            </p>

            {visible.length === 0 ? (
                <p className="mt-3 text-sm text-brand-muted">No themes match those filters.</p>
            ) : (
                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {visible.map(([slug, theme]) => (
                        <ThemeOption
                            key={slug}
                            theme={theme}
                            selected={slug === selected}
                            current={slug === currentTheme}
                            customised={customised}
                            isDefault={slug === defaultTheme}
                            onSelect={() => onSelect(slug)}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

/* ---------- Customise tab ---------- */

function ColorField({ label, value, onChange, error }) {
    return (
        <div>
            <label className="flex items-center justify-between gap-3">
                <span className="text-sm text-brand-text">{label}</span>
                <span className="flex items-center gap-2">
                    <input
                        type="color"
                        value={/^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#000000'}
                        onChange={(e) => onChange(e.target.value.toUpperCase())}
                        aria-label={`${label} colour picker`}
                        className="h-9 w-9 cursor-pointer rounded-lg border border-brand-border bg-brand-card p-0.5"
                    />
                    <input
                        type="text"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        maxLength={7}
                        aria-label={`${label} hex code`}
                        className="w-24 rounded-lg border border-brand-border bg-brand-card px-2 py-1.5 font-mono text-xs text-brand-text outline-none focus:border-brand-accent"
                    />
                </span>
            </label>
            <FieldError message={error} />
        </div>
    );
}

function CustomiseTab({ fields, setField, fonts, radiusPresets, baseName, hasCustom, errors }) {
    return (
        <Panel
            title="Customise your theme"
            description={`Starting from ${baseName}. Change any colour, font or corner style, then save.`}
        >
            <div className="space-y-6">
                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Colours</h3>
                    <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-x-8">
                        {CUSTOM_COLOR_FIELDS.map(([key, label]) => (
                            <ColorField key={key} label={label} value={fields[key]} onChange={(v) => setField(key, v)} error={errors[key]} />
                        ))}
                    </div>
                </div>

                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Fonts & corners</h3>
                    <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label className="block">
                            <span className="mb-1.5 block text-sm text-brand-text">Heading font</span>
                            <select value={fields.heading_font_name} onChange={(e) => setField('heading_font_name', e.target.value)} className={inputClass}>
                                {fonts.map((f) => (
                                    <option key={f.name} value={f.name}>
                                        {f.name}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.heading_font_name} />
                        </label>
                        <label className="block">
                            <span className="mb-1.5 block text-sm text-brand-text">Body font</span>
                            <select value={fields.body_font_name} onChange={(e) => setField('body_font_name', e.target.value)} className={inputClass}>
                                {fonts.map((f) => (
                                    <option key={f.name} value={f.name}>
                                        {f.name}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.body_font_name} />
                        </label>
                        <label className="block">
                            <span className="mb-1.5 block text-sm text-brand-text">Card corners</span>
                            <select value={fields.radius} onChange={(e) => setField('radius', e.target.value)} className={inputClass}>
                                {radiusPresets.map((preset) => (
                                    <option key={preset} value={preset}>
                                        {preset === '9999px' ? 'Fully round' : preset === '2px' ? 'Square (2px)' : preset}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.radius} />
                        </label>
                    </div>
                </div>

                {hasCustom && (
                    <p className="rounded-xl bg-brand-bg px-3 py-2 text-xs text-brand-muted">
                        Your shop is using these custom colours. Picking a different theme on the Themes tab replaces them.
                    </p>
                )}
            </div>
        </Panel>
    );
}

/* ---------- Stamp icon tab ---------- */

function StampTab({ icon, onSelect, currentIcon }) {
    return (
        <Panel title="Stamp icon" description="What customers see inside each stamp they collect.">
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 xl:grid-cols-6">
                {STAMP_ICONS.map(({ key, label, Icon }) => {
                    const selected = key === icon;

                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => onSelect(key)}
                            aria-pressed={selected}
                            className={`relative flex flex-col items-center gap-2 rounded-xl border px-2 py-3 text-xs font-medium transition ${
                                selected ? 'border-brand-accent bg-brand-accent/5 ring-2 ring-brand-accent' : 'border-brand-border hover:bg-brand-bg'
                            }`}
                        >
                            <span
                                className={`flex h-10 w-10 items-center justify-center rounded-full ${
                                    selected ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-text'
                                }`}
                            >
                                <Icon className="h-5 w-5" />
                            </span>
                            <span className="text-brand-text">{label}</span>
                            {key === currentIcon && (
                                <span className="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-green-600 text-white">
                                    <LuCheck className="h-2.5 w-2.5" />
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </Panel>
    );
}

/* ---------- Sign-up icon tab ---------- */

/** The small icon between the sign-up form and the footer, grouped by kind of business. */
function SignupIconTab({ icon, onSelect, currentIcon }) {
    return (
        <Panel title="Sign-up icon" description="The small icon on your customer sign-up screen, under the Start my card button. Pick one that fits your business.">
            {/* How it looks on the sign-up screen, same size as there. */}
            <div className="mb-5 flex items-center justify-center gap-3 rounded-xl bg-brand-bg py-4" aria-hidden="true">
                <span className="h-px w-20 bg-gradient-to-r from-transparent to-brand-accent/40" />
                <SignupIcon icon={icon} className="h-5 w-5 text-brand-accent" />
                <span className="h-px w-20 bg-gradient-to-l from-transparent to-brand-accent/40" />
            </div>

            <div className="space-y-5">
                {SIGNUP_ICON_GROUPS.map((group) => (
                    <div key={group.label}>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">{group.label}</p>
                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 xl:grid-cols-6">
                            {group.icons.map(([key, label, Icon]) => {
                                const selected = key === icon;

                                return (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => onSelect(key)}
                                        aria-pressed={selected}
                                        className={`relative flex flex-col items-center gap-2 rounded-xl border px-2 py-3 text-xs font-medium transition ${
                                            selected ? 'border-brand-accent bg-brand-accent/5 ring-2 ring-brand-accent' : 'border-brand-border hover:bg-brand-bg'
                                        }`}
                                    >
                                        <span
                                            className={`flex h-10 w-10 items-center justify-center rounded-full ${
                                                selected ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-text'
                                            }`}
                                        >
                                            <Icon className="h-5 w-5" />
                                        </span>
                                        <span className="text-center text-brand-text">{label}</span>
                                        {key === currentIcon && (
                                            <span className="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-green-600 text-white">
                                                <LuCheck className="h-2.5 w-2.5" />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
        </Panel>
    );
}

/* ---------- Logo (in the Banner & logo tab) ---------- */

/**
 * The shop's logo for the round badge on the customer card page and sign-up
 * screen. Uploads as soon as a file is picked (no separate save step).
 */
function LogoPanel({ logoUrl, error, confirm }) {
    const input = useRef(null);
    const [busy, setBusy] = useState(false);

    function upload(file) {
        if (!file) return;
        router.post('/dashboard/theme/logo', { logo: file }, { forceFormData: true, preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    }

    async function remove() {
        const ok = await confirm({
            title: 'Remove your logo?',
            message: 'Your card page shows the shop icon instead.',
            confirmLabel: 'Remove logo',
            danger: true,
        });
        if (ok) router.delete('/dashboard/theme/logo', { preserveScroll: true });
    }

    return (
        <Panel title="Logo" description="Shown in the round badge at the top of your card page and sign-up screen. A square image works best.">
            <div className="flex items-center gap-4">
                {/* Same look as the badge customers see. */}
                <span className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-brand-bg bg-brand-card ring-1 ring-brand-border">
                    {logoUrl ? <img src={logoUrl} alt="Your logo" className="h-full w-full object-cover" /> : <LuStore className="h-9 w-9 text-brand-accent" />}
                </span>
                <div className="flex flex-wrap gap-2">
                    <input
                        ref={input}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        onChange={(e) => {
                            upload(e.target.files?.[0]);
                            e.target.value = '';
                        }}
                    />
                    <button type="button" onClick={() => input.current?.click()} disabled={busy} className={primaryButton}>
                        <LuImagePlus className="h-4 w-4" /> {busy ? 'Uploading…' : logoUrl ? 'Replace logo' : 'Upload logo'}
                    </button>
                    {logoUrl && (
                        <button type="button" onClick={remove} disabled={busy} className={`${secondaryButton} text-red-600`}>
                            <LuTrash2 className="h-4 w-4" /> Remove
                        </button>
                    )}
                </div>
            </div>
            <FieldError message={error} />
            {!error && <p className="mt-3 text-xs text-brand-muted">JPG, PNG or WebP · up to 2 MB · at least 120 × 120 px.</p>}
        </Panel>
    );
}

/* ---------- Header text (in the Banner & logo tab) ---------- */

/** Shop name / reward colour, banner tint and title shadow - so the header reads on any photo. */
function HeaderPanel({ style, onChange, saved, maxTint, hasBanner, errors }) {
    const [saving, setSaving] = useState(false);
    const dirty = JSON.stringify(style) !== JSON.stringify(saved);
    const set = (key, value) => onChange({ ...style, [key]: value });

    return (
        <Panel title="Header text" description="Make your shop name readable on your banner. Changes show in the preview.">
            <div className="space-y-4">
                <ColorField label="Title colour" value={style.text_color} onChange={(v) => set('text_color', v)} error={errors.text_color} />

                <div className={hasBanner ? '' : 'opacity-50'}>
                    <label className="flex items-center justify-between gap-3">
                        <span className="text-sm text-brand-text">Banner darkness</span>
                        <span className="text-xs tabular-nums text-brand-muted">{style.tint}%</span>
                    </label>
                    <input
                        type="range"
                        min={0}
                        max={maxTint}
                        step={5}
                        value={style.tint}
                        disabled={!hasBanner}
                        onChange={(e) => set('tint', Number(e.target.value))}
                        aria-label="Banner darkness"
                        className="mt-2 w-full accent-brand-accent"
                    />
                    {!hasBanner && <p className="text-xs text-brand-muted">Upload a banner photo to use this.</p>}
                    <FieldError message={errors.tint} />
                </div>

                <Switch checked={style.shadow} onChange={(v) => set('shadow', v)} label="Title shadow" description="A soft shadow behind the text." />

                <div className="flex flex-wrap gap-2 pt-1">
                    <button
                        type="button"
                        disabled={!dirty || saving}
                        onClick={() =>
                            router.put('/dashboard/theme/header', style, { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) })
                        }
                        className={primaryButton}
                    >
                        {saving ? 'Saving…' : 'Save header'}
                    </button>
                    {JSON.stringify(style) !== JSON.stringify(HEADER_STYLE_DEFAULTS) && (
                        <button type="button" onClick={() => onChange(HEADER_STYLE_DEFAULTS)} disabled={saving} className={secondaryButton}>
                            <LuRotateCcw className="h-4 w-4" /> Defaults
                        </button>
                    )}
                </div>
            </div>
        </Panel>
    );
}

/* ---------- Banner tab ---------- */

function BannerTab({ bannerUrl, shown, file, onFile, error, progress, onRemove, saving }) {
    const [dragging, setDragging] = useState(false);

    function pick(list) {
        const picked = list?.[0];
        if (picked) onFile(picked);
    }

    return (
        <Panel
            title="Banner image"
            description="A photo of your shop, food or drinks. It's the header of your card page and the blurred background behind the sign-up form."
        >
            {shown && (
                <div className="relative mb-4 overflow-hidden rounded-xl border border-brand-border bg-neutral-900">
                    <img src={shown} alt="Banner preview" className="h-40 w-full object-cover sm:h-48" />
                    {file && (
                        <span className="absolute left-2 top-2 rounded-full bg-black/60 px-2.5 py-1 text-[11px] font-medium text-white">
                            Not uploaded yet
                        </span>
                    )}
                </div>
            )}

            <label
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    pick(e.dataTransfer.files);
                }}
                className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors ${
                    dragging ? 'border-brand-accent bg-brand-accent/5' : 'border-brand-border hover:bg-brand-bg'
                }`}
            >
                <LuImagePlus className="h-8 w-8 text-brand-muted" />
                <span className="text-sm font-medium text-brand-text">{shown ? 'Choose a different photo' : 'Choose a photo'}</span>
                <span className="text-xs text-brand-muted">JPG, PNG or WebP, up to 4 MB. A wide photo (e.g. 1600 × 600) looks best.</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(e) => pick(e.target.files)} />
            </label>

            <FieldError message={error} />

            {progress !== null && (
                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-brand-border" aria-label={`Uploading ${progress}%`}>
                    <div className="h-full rounded-full bg-brand-accent transition-all" style={{ width: `${progress}%` }} />
                </div>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
                {file && (
                    <button type="button" onClick={() => onFile(null)} disabled={saving} className={secondaryButton}>
                        Cancel
                    </button>
                )}
                {bannerUrl && !file && (
                    <button type="button" onClick={onRemove} disabled={saving} className={`${secondaryButton} text-red-600`}>
                        <LuTrash2 className="h-4 w-4" /> Remove banner
                    </button>
                )}
            </div>
        </Panel>
    );
}

/* ---------- Shared ---------- */

/** Editor starting values: the saved customisation, else the current theme's own values. */
function initialFields(customTheme, appliedTheme, fonts, radiusPresets) {
    if (customTheme) return customTheme;

    const known = (name) => (fonts.some((f) => f.name === name) ? name : fonts[0].name);

    return {
        ...Object.fromEntries(CUSTOM_COLOR_FIELDS.map(([key]) => [key, appliedTheme[key]])),
        heading_font_name: known(fontNameOf(appliedTheme.heading_font)),
        body_font_name: known(fontNameOf(appliedTheme.body_font)),
        radius: radiusPresets.includes(appliedTheme.radius) ? appliedTheme.radius : '12px',
    };
}

export default function Theme({
    shop,
    currentTheme,
    appliedTheme,
    customTheme,
    defaultTheme,
    themeInDashboard,
    stampIcon,
    signupIcon,
    headerStyle,
    maxTint,
    bannerUrl,
    logoUrl,
    themes,
    availableFonts,
    radiusPresets,
}) {
    const { errors } = usePage().props;
    const [tab, setTab] = useState('themes');
    const [selected, setSelected] = useState(currentTheme);
    const [icon, setIcon] = useState(stampIcon);
    const [decoIcon, setDecoIcon] = useState(signupIcon);
    const [header, setHeader] = useState(headerStyle);
    const [bannerFile, setBannerFile] = useState(null);
    const [uploadProgress, setUploadProgress] = useState(null);
    const [fields, setFields] = useState(() => initialFields(customTheme, appliedTheme, availableFonts, radiusPresets));
    const [saving, setSaving] = useState(false);
    const previewRef = useRef(null);

    // After a save or reset, the editors follow the new saved state.
    useEffect(() => setSelected(currentTheme), [currentTheme]);
    useEffect(() => setIcon(stampIcon), [stampIcon]);
    useEffect(() => setDecoIcon(signupIcon), [signupIcon]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => setHeader(headerStyle), [JSON.stringify(headerStyle)]);
    useEffect(
        () => setFields(initialFields(customTheme, appliedTheme, availableFonts, radiusPresets)),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [JSON.stringify(customTheme), JSON.stringify(appliedTheme)],
    );

    const draft = withCustomisation(appliedTheme, fields, availableFonts);
    const previewTheme = tab === 'customise' ? draft : tab === 'themes' && selected !== currentTheme ? themes[selected] : appliedTheme;
    const previewIcon = tab === 'stamp' ? icon : stampIcon;
    // A picked-but-not-uploaded photo shows in the preview straight away.
    const previewBanner = useMemo(() => (bannerFile ? URL.createObjectURL(bannerFile) : bannerUrl), [bannerFile, bannerUrl]);
    useEffect(
        () => () => {
            if (previewBanner?.startsWith('blob:')) URL.revokeObjectURL(previewBanner);
        },
        [previewBanner],
    );
    useThemeFonts(previewTheme);

    const initial = initialFields(customTheme, appliedTheme, availableFonts, radiusPresets);
    const dirty = {
        themes: selected !== currentTheme,
        customise: JSON.stringify(fields) !== JSON.stringify(initial),
        stamp: icon !== stampIcon,
        signup: decoIcon !== signupIcon,
        banner: bannerFile !== null,
    }[tab];

    const busy = { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) };
    const [confirm, confirmDialog] = useConfirm();

    async function save() {
        if (tab === 'themes') {
            if (
                customTheme &&
                !(await confirm({
                    title: 'Use this theme?',
                    message: 'Using a new theme replaces your custom colours.',
                    confirmLabel: 'Use this theme',
                }))
            ) {
                return;
            }
            router.put('/dashboard/theme', { theme: selected }, busy);
        } else if (tab === 'customise') {
            router.put('/dashboard/theme/custom', fields, busy);
        } else if (tab === 'stamp') {
            router.put('/dashboard/theme/stamp-icon', { stamp_icon: icon }, busy);
        } else if (tab === 'signup') {
            router.put('/dashboard/theme/signup-icon', { signup_icon: decoIcon }, busy);
        } else {
            // POST + FormData: file uploads can't go through a PUT.
            router.post(
                '/dashboard/theme/banner',
                { banner: bannerFile },
                {
                    ...busy,
                    forceFormData: true,
                    onProgress: (e) => setUploadProgress(e?.percentage ?? null),
                    onSuccess: () => setBannerFile(null),
                    onFinish: () => {
                        setSaving(false);
                        setUploadProgress(null);
                    },
                },
            );
        }
    }

    async function removeBanner() {
        const ok = await confirm({
            title: 'Remove your banner photo?',
            message: 'Your card page goes back to the colour banner.',
            confirmLabel: 'Remove photo',
            danger: true,
        });
        if (ok) router.delete('/dashboard/theme/banner', busy);
    }

    const saveLabel = { themes: 'Use this theme', customise: 'Save my colours', stamp: 'Use this icon', signup: 'Use this icon', banner: 'Upload banner' }[tab];

    async function discardCustom() {
        const ok = await confirm({
            title: 'Remove your custom colours?',
            message: `Your card page goes back to ${themes[currentTheme].name} as it was.`,
            confirmLabel: 'Remove colours',
            danger: true,
        });
        if (ok) router.delete('/dashboard/theme/custom', busy);
    }

    async function resetToDefault() {
        const ok = await confirm({
            title: 'Go back to the default look?',
            message: 'Your customer page returns to the default theme. Your custom colours are removed too.',
            confirmLabel: 'Reset to default',
            danger: true,
        });
        if (ok) router.delete('/dashboard/theme', busy);
    }

    const setField = (key, value) => setFields((f) => ({ ...f, [key]: value }));

    return (
        <OwnerLayout shop={shop} title="Theme" description="How your customer card page looks: theme, your own colours and the stamp icon.">
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
                {/* Preview first on phones, sticky on the right on desktop. */}
                <div ref={previewRef} className="lg:order-2">
                    <div className="lg:sticky lg:top-8">
                        <Panel
                            title="Preview"
                            description={tab === 'customise' ? `${themes[currentTheme].name} · your colours` : previewTheme.name}
                        >
                            <PhonePreview theme={previewTheme} shop={shop} stampIcon={previewIcon} bannerUrl={previewBanner} logoUrl={logoUrl} headerStyle={header} />

                            <button type="button" onClick={save} disabled={!dirty || saving} className={`${primaryButton} mt-5 hidden w-full lg:flex`}>
                                {saving ? 'Saving…' : dirty ? saveLabel : 'No changes to save'}
                            </button>

                            <div className="mt-5 space-y-3 border-t border-brand-border pt-4">
                                <Switch
                                    checked={themeInDashboard}
                                    onChange={(enabled) => router.put('/dashboard/theme/dashboard', { enabled }, busy)}
                                    disabled={saving}
                                    label="Use this theme in my dashboard too"
                                    description="Your dashboard shows your current theme instead of the standard look."
                                />
                                {customTheme && (
                                    <button type="button" onClick={discardCustom} disabled={saving} className={`${secondaryButton} w-full`}>
                                        <LuRotateCcw className="h-4 w-4" /> Remove my custom colours
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={resetToDefault}
                                    disabled={saving || (currentTheme === defaultTheme && !customTheme)}
                                    className={`${secondaryButton} w-full`}
                                >
                                    <LuRotateCcw className="h-4 w-4" /> Reset to default theme
                                </button>
                            </div>

                            <a
                                href={`/s/${shop.slug}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mt-3 block text-center text-xs font-medium text-brand-accent"
                            >
                                Open your live card page
                            </a>
                        </Panel>
                    </div>
                </div>

                <div className="min-w-0 lg:order-1">
                    <div className="mb-5 flex min-w-0 gap-1 rounded-xl border border-brand-border bg-brand-card p-1" role="tablist">
                        {TABS.map(({ id, label, icon: Icon }) => (
                            <button
                                key={id}
                                type="button"
                                role="tab"
                                aria-selected={tab === id}
                                onClick={() => setTab(id)}
                                className={`flex min-w-0 items-center justify-center gap-1 rounded-lg px-1.5 py-2 text-sm font-medium transition-[flex-grow,color,background-color] duration-300 ease-out sm:gap-2 sm:px-2 lg:flex-1 ${
                                    tab === id ? 'flex-[2.5]' : 'flex-1'
                                } ${
                                    tab === id ? 'bg-brand-accent text-brand-accent-text' : 'text-brand-muted hover:bg-brand-bg hover:text-brand-text'
                                }`}
                            >
                                <Icon className="h-4 w-4 shrink-0" />
                                <span className={`truncate ${tab === id ? 'inline' : 'hidden'} lg:inline`}>{label}</span>
                            </button>
                        ))}
                    </div>

                    {tab === 'themes' && (
                        <ThemesTab
                            themes={themes}
                            selected={selected}
                            onSelect={setSelected}
                            currentTheme={currentTheme}
                            customised={Boolean(customTheme)}
                            defaultTheme={defaultTheme}
                        />
                    )}
                    {tab === 'customise' && (
                        <CustomiseTab
                            fields={fields}
                            setField={setField}
                            fonts={availableFonts}
                            radiusPresets={radiusPresets}
                            baseName={themes[currentTheme].name}
                            hasCustom={Boolean(customTheme)}
                            errors={errors ?? {}}
                        />
                    )}
                    {tab === 'stamp' && <StampTab icon={icon} onSelect={setIcon} currentIcon={stampIcon} />}
                    {tab === 'signup' && <SignupIconTab icon={decoIcon} onSelect={setDecoIcon} currentIcon={signupIcon} />}
                    {tab === 'banner' && (
                        <div className="space-y-6">
                            <BannerTab
                                bannerUrl={bannerUrl}
                                shown={previewBanner}
                                file={bannerFile}
                                onFile={setBannerFile}
                                error={errors?.banner}
                                progress={uploadProgress}
                                onRemove={removeBanner}
                                saving={saving}
                            />
                            <HeaderPanel style={header} onChange={setHeader} saved={headerStyle} maxTint={maxTint} hasBanner={Boolean(previewBanner)} errors={errors ?? {}} />
                            <LogoPanel logoUrl={logoUrl} error={errors?.logo} confirm={confirm} />
                        </div>
                    )}
                </div>
            </div>

            {/* Phones: the preview is scrolled away while editing, so keep the action in reach. */}
            {dirty && (
                <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-10 border-t border-brand-border bg-brand-card/95 px-4 py-3 backdrop-blur lg:hidden">
                    <div className="mx-auto flex max-w-xl items-center gap-2">
                        <p className="min-w-0 flex-1 truncate text-sm font-semibold text-brand-text">Unsaved changes</p>
                        <button
                            type="button"
                            onClick={() => previewRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                            className={`${secondaryButton} shrink-0`}
                        >
                            Preview
                        </button>
                        <button type="button" onClick={save} disabled={saving} className={`${primaryButton} shrink-0`}>
                            {saving ? 'Saving…' : saveLabel}
                        </button>
                    </div>
                </div>
            )}
            {confirmDialog}
        </OwnerLayout>
    );
}
