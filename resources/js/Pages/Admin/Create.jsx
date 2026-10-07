import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { LuArrowLeft, LuCheck, LuEye, LuEyeOff, LuLoaderCircle, LuRefreshCw } from 'react-icons/lu';
import CountrySelect from '@/Components/CountrySelect';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { MAX_STAMPS, MIN_STAMPS, SlugInput, StampStepper, slugify } from '@/Components/Dashboard/ShopFields';
import { FieldError, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import StampGrid from '@/Components/StampGrid';
import { STAMP_ICONS, StampIcon } from '@/lib/stampIcons';
import { THEME_CATEGORY_LABELS, themeVars, useThemeFonts } from '@/lib/theme';

function Field({ id, label, hint, error, children }) {
    return (
        <div className="min-w-0">
            <label htmlFor={id} className="block text-sm font-medium text-brand-text">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {hint && !error && <div className="mt-1 text-xs text-brand-muted">{hint}</div>}
            <FieldError message={error} />
        </div>
    );
}

/** One row of the form: what it's about on the left, its fields on the right. */
function Section({ title, description, children }) {
    return (
        <section className="grid gap-4 p-5 lg:grid-cols-[170px_minmax(0,1fr)] lg:gap-8">
            <div>
                <h2 className="font-heading text-sm font-semibold text-brand-text">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-brand-muted">{description}</p>}
            </div>
            <div className="min-w-0 space-y-4">{children}</div>
        </section>
    );
}

/** 14 characters, no look-alikes (0/O, 1/l/I), easy to read out to an owner. */
function generatePassword() {
    const alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const bytes = crypto.getRandomValues(new Uint32Array(14));

    return Array.from(bytes, (b) => alphabet[b % alphabet.length]).join('');
}

/**
 * Asks the server whether the card link is free (debounced). While the admin
 * hasn't typed their own link, a taken one is swapped for the free suggestion.
 */
function useSlugCheck(slug, name, touched, onSuggest) {
    const [status, setStatus] = useState(null);
    // Free links built from the shop name; kept between checks so they don't flicker.
    const [ideas, setIdeas] = useState([]);

    useEffect(() => {
        if (!slug) {
            setStatus(null);
            setIdeas([]);
            return;
        }

        setStatus({ checking: true });
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const query = new URLSearchParams({ slug, name });
                const res = await fetch(`/admin/shops/slug?${query}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!res.ok) throw new Error();
                const body = await res.json();
                setIdeas(body.ideas ?? []);

                if (!body.available && !touched) {
                    onSuggest(body.suggestion);
                } else {
                    setStatus(body);
                }
            } catch (e) {
                // Offline / aborted: no hint; the unique rule still checks on save.
                if (e.name !== 'AbortError') setStatus(null);
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [slug, name, touched]);

    return [status, ideas];
}

function SlugIdeas({ ideas, current, onPick }) {
    const shown = ideas.filter((idea) => idea !== current);
    if (shown.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-xs text-brand-muted">Ideas</span>
            {shown.map((idea) => (
                <button
                    key={idea}
                    type="button"
                    onClick={() => onPick(idea)}
                    className="rounded-full border border-brand-border px-2.5 py-0.5 font-mono text-xs text-brand-text transition-colors hover:border-brand-accent hover:bg-brand-bg"
                >
                    {idea}
                </button>
            ))}
        </div>
    );
}

function SlugHint({ status, onUse }) {
    if (!status) return null;
    if (status.checking) {
        return (
            <span className="inline-flex items-center gap-1">
                <LuLoaderCircle className="h-3 w-3 animate-spin" /> Checking…
            </span>
        );
    }
    if (status.available) {
        return (
            <span className="inline-flex items-center gap-1 text-green-700">
                <LuCheck className="h-3 w-3" /> Available
            </span>
        );
    }

    return (
        <span className="text-amber-700">
            Already taken.{' '}
            <button type="button" onClick={() => onUse(status.suggestion)} className="font-semibold underline underline-offset-2">
                Use {status.suggestion}
            </button>
        </span>
    );
}

/** The customer's card page in miniature, wearing the picked theme. */
function CardPreview({ theme, name, reward, slug, maxStamps, stampIcon }) {
    useThemeFonts(theme);
    const total = Math.min(MAX_STAMPS, Math.max(MIN_STAMPS, maxStamps || MIN_STAMPS));
    const filled = Math.min(2, total);

    return (
        <div style={themeVars(theme)} className="overflow-hidden rounded-2xl border border-brand-border bg-brand-bg font-sans text-brand-text">
            <div className="flex items-center gap-3 bg-brand-deep px-4 pb-9 pt-8 text-white">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-brand-accent">
                    <StampIcon icon={stampIcon} className="h-5 w-5" />
                </span>
                <div className="min-w-0">
                    <p className="truncate font-heading text-base font-bold leading-tight">{name || 'Your shop'}</p>
                    <p className="truncate text-xs text-white/80">{reward || 'Your reward'}</p>
                </div>
            </div>
            <div className="-mt-5 px-3 pb-4">
                <div className="rounded-brand border border-brand-border bg-brand-card p-3.5">
                    <p className="font-heading text-lg font-bold tabular-nums">
                        {filled}
                        <span className="text-sm font-medium text-brand-muted"> / {total}</span>
                    </p>
                    <StampGrid className="mt-2" size="sm" animate={false} total={total} stamps={filled} icon={stampIcon} />
                </div>
                <p className="mt-3 truncate text-center font-mono text-[11px] text-brand-muted">
                    {window.location.host}/s/{slug || 'your-shop'}
                </p>
            </div>
        </div>
    );
}

function Chip({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                active ? 'border-brand-accent bg-brand-accent text-brand-accent-text' : 'border-brand-border text-brand-text hover:bg-brand-bg'
            }`}
        >
            {children}
        </button>
    );
}

function ThemePicker({ themes, value, onChange, defaultTheme }) {
    const [category, setCategory] = useState('all');
    const categories = useMemo(() => [...new Set(Object.values(themes).map((t) => t.category))], [themes]);
    const visible = Object.entries(themes).filter(([, t]) => category === 'all' || t.category === category);

    return (
        <div>
            <div className="no-scrollbar flex gap-1.5 overflow-x-auto">
                <Chip active={category === 'all'} onClick={() => setCategory('all')}>
                    All
                </Chip>
                {categories.map((c) => (
                    <Chip key={c} active={category === c} onClick={() => setCategory(c)}>
                        {THEME_CATEGORY_LABELS[c] ?? c}
                    </Chip>
                ))}
            </div>

            <div className="mt-3 grid max-h-72 grid-cols-2 gap-2 overflow-y-auto p-0.5 sm:grid-cols-3 2xl:grid-cols-4">
                {visible.map(([slug, theme]) => {
                    const selected = slug === value;

                    return (
                        <button
                            key={slug}
                            type="button"
                            onClick={() => onChange(slug)}
                            aria-pressed={selected}
                            className={`flex items-center gap-2.5 rounded-xl border p-2 text-left transition ${
                                selected ? 'border-brand-accent ring-2 ring-brand-accent' : 'border-brand-border hover:bg-brand-bg'
                            }`}
                        >
                            {/* Dark surface, accent, page - the three colours a card is mostly made of. */}
                            <span className="flex h-8 w-8 shrink-0 overflow-hidden rounded-lg border" style={{ borderColor: theme.border }}>
                                <span className="flex-1" style={{ background: theme.deep }} />
                                <span className="flex-1" style={{ background: theme.accent }} />
                                <span className="flex-1" style={{ background: theme.page_bg }} />
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-medium text-brand-text">{theme.name}</span>
                                {slug === defaultTheme && <span className="block text-[10px] text-brand-muted">Default</span>}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export default function Create({ themes, defaultTheme, countries }) {
    const { data, setData, post, processing, errors } = useForm({
        shop_name: '',
        shop_slug: '',
        shop_country: 'GB',
        owner_email: '',
        owner_password: '',
        shop_max_stamps: 8,
        shop_reward_title: '',
        shop_stamp_icon: 'check',
        shop_theme: defaultTheme,
    });
    // The link follows the shop name until the admin types their own.
    const [slugTouched, setSlugTouched] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [slugStatus, slugIdeas] = useSlugCheck(data.shop_slug, data.shop_name, slugTouched, (suggestion) => setData('shop_slug', suggestion));

    function handleShopNameChange(value) {
        setData((prev) => ({
            ...prev,
            shop_name: value,
            shop_slug: slugTouched ? prev.shop_slug : slugify(value),
        }));
    }

    function handleSlugChange(value) {
        setSlugTouched(value !== '');
        setData('shop_slug', value || slugify(data.shop_name));
    }

    function handlePickIdea(idea) {
        setSlugTouched(true);
        setData('shop_slug', idea);
    }

    function handleGenerate() {
        setData('owner_password', generatePassword());
        setShowPassword(true);
    }

    function handleSubmit(e) {
        e.preventDefault();
        post('/admin/shops');
    }

    return (
        <AdminLayout
            title="Add shop"
            description="The shop, its owner login, stamp card and look - all in one go."
            actions={
                <Link href="/admin" className={secondaryButton}>
                    <LuArrowLeft className="h-4 w-4" /> Back to shops
                </Link>
            }
        >
            <form onSubmit={handleSubmit} noValidate className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
                <div className="min-w-0 divide-y divide-brand-border rounded-2xl border border-brand-border bg-brand-card">
                    <Section title="Shop" description="Its name and the link customers open.">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field id="shop_name" label="Shop name" error={errors.shop_name}>
                                <input
                                    id="shop_name"
                                    value={data.shop_name}
                                    onChange={(e) => handleShopNameChange(e.target.value)}
                                    placeholder="e.g. Corner Bakery"
                                    autoFocus
                                    className={inputClass}
                                />
                            </Field>
                            <Field
                                id="shop_slug"
                                label="Card link"
                                hint={<SlugHint status={slugStatus} onUse={(s) => setData('shop_slug', s)} />}
                                error={errors.shop_slug}
                            >
                                <SlugInput id="shop_slug" value={data.shop_slug} onChange={handleSlugChange} placeholder="corner-bakery" />
                            </Field>
                        </div>
                        <SlugIdeas ideas={slugIdeas} current={data.shop_slug} onPick={handlePickIdea} />
                        <div className="mt-4 max-w-sm">
                            <Field
                                id="shop_country"
                                label="Country"
                                hint={data.shop_country === 'GB' ? null : "Can't order a counter display - the owner downloads their QR codes."}
                                error={errors.shop_country}
                            >
                                <CountrySelect
                                    id="shop_country"
                                    value={data.shop_country}
                                    onChange={(code) => setData('shop_country', code)}
                                    countries={countries}
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </Section>

                    <Section title="Owner login" description="Pass these on to the owner.">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field id="owner_email" label="Email" error={errors.owner_email}>
                                <input
                                    id="owner_email"
                                    type="email"
                                    value={data.owner_email}
                                    onChange={(e) => setData('owner_email', e.target.value)}
                                    placeholder="owner@example.com"
                                    autoComplete="off"
                                    className={inputClass}
                                />
                            </Field>
                            <Field id="owner_password" label="Password" hint="At least 8 characters." error={errors.owner_password}>
                                <div className="flex gap-2">
                                    <div className="relative min-w-0 flex-1">
                                        <input
                                            id="owner_password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.owner_password}
                                            onChange={(e) => setData('owner_password', e.target.value)}
                                            autoComplete="new-password"
                                            className={`${inputClass} pr-10 ${showPassword ? 'font-mono' : ''}`}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((s) => !s)}
                                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                                            className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-brand-muted hover:text-brand-text"
                                        >
                                            {showPassword ? <LuEyeOff className="h-4 w-4" /> : <LuEye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                    <button type="button" onClick={handleGenerate} className={secondaryButton} title="Generate a password">
                                        <LuRefreshCw className="h-4 w-4" />
                                        <span className="hidden sm:inline">Generate</span>
                                    </button>
                                </div>
                            </Field>
                        </div>
                    </Section>

                    <Section title="Stamp card" description="Stamps to the reward, and how each one looks.">
                        <div className="grid gap-4 md:grid-cols-[auto_minmax(0,1fr)]">
                            <Field id="shop_max_stamps" label="Stamps needed" error={errors.shop_max_stamps}>
                                <StampStepper id="shop_max_stamps" value={data.shop_max_stamps} onChange={(v) => setData('shop_max_stamps', v)} />
                            </Field>
                            <Field id="shop_reward_title" label="Reward" error={errors.shop_reward_title}>
                                <input
                                    id="shop_reward_title"
                                    value={data.shop_reward_title}
                                    onChange={(e) => setData('shop_reward_title', e.target.value)}
                                    placeholder={`Free coffee after ${data.shop_max_stamps} stamps`}
                                    className={inputClass}
                                />
                            </Field>
                        </div>

                        <div>
                            <p className="text-sm font-medium text-brand-text">Stamp icon</p>
                            <div className="mt-1.5 flex flex-wrap gap-1.5">
                                {STAMP_ICONS.map(({ key, label, Icon }) => {
                                    const selected = key === data.shop_stamp_icon;

                                    return (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => setData('shop_stamp_icon', key)}
                                            aria-pressed={selected}
                                            aria-label={label}
                                            title={label}
                                            className={`flex h-9 w-9 items-center justify-center rounded-full transition ${
                                                selected ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-text hover:bg-brand-border'
                                            }`}
                                        >
                                            <Icon className="h-[18px] w-[18px]" />
                                        </button>
                                    );
                                })}
                            </div>
                            <FieldError message={errors.shop_stamp_icon} />
                        </div>
                    </Section>

                    <Section title="Theme" description="The owner can change it later.">
                        <ThemePicker themes={themes} value={data.shop_theme} onChange={(t) => setData('shop_theme', t)} defaultTheme={defaultTheme} />
                        <FieldError message={errors.shop_theme} />
                    </Section>
                </div>

                <aside className="space-y-3 xl:sticky xl:top-10">
                    <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">Preview · {themes[data.shop_theme]?.name}</p>
                    <CardPreview
                        theme={themes[data.shop_theme]}
                        name={data.shop_name}
                        reward={data.shop_reward_title}
                        slug={data.shop_slug}
                        maxStamps={data.shop_max_stamps}
                        stampIcon={data.shop_stamp_icon}
                    />
                    <button type="submit" disabled={processing} className={`${primaryButton} w-full py-3`}>
                        {processing ? 'Creating…' : 'Create shop'}
                    </button>
                </aside>
            </form>
        </AdminLayout>
    );
}
