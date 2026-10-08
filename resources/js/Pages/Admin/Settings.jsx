import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FcGoogle } from 'react-icons/fc';
import { LuCircleAlert, LuHand, LuImage, LuKeyRound, LuLandmark, LuLayoutDashboard, LuMail, LuPalette, LuRotateCcw, LuSettings, LuSparkles, LuStore, LuToggleRight, LuTriangleAlert } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { navIcon } from '@/lib/navIcons';
import { CopyButton, FieldError, navSurface, Panel, primaryButton, secondaryButton, sideLinkClass, Switch } from '@/Components/Dashboard/Ui';

function StatusChip({ tone, children }) {
    const tones = {
        live: 'bg-emerald-500/10 text-emerald-700',
        off: 'bg-brand-bg text-brand-muted ring-1 ring-brand-border',
        warn: 'bg-amber-500/10 text-amber-700',
    };

    return <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${tones[tone]}`}>{children}</span>;
}

function MethodRow({ icon, title, description, status, control, children }) {
    return (
        <div className="px-5 py-5">
            <div className="flex items-start gap-4">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-brand-border bg-brand-card">{icon}</span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-semibold text-brand-text">{title}</p>
                        {status}
                    </div>
                    <p className="mt-0.5 text-sm text-brand-muted">{description}</p>
                </div>
                {control}
            </div>
            {children && <div className="mt-4 sm:pl-14">{children}</div>}
        </div>
    );
}

function GoogleSetup({ google }) {
    return (
        <div className="space-y-3 rounded-xl bg-brand-bg p-4 text-sm">
            <div className="flex items-start gap-2.5">
                {google.configured ? (
                    <LuKeyRound className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                ) : (
                    <LuCircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                )}
                {google.configured ? (
                    <p className="text-brand-muted">
                        Keys found in <code className="font-mono text-brand-text">.env</code> · Client ID{' '}
                        <code className="font-mono text-brand-text">{google.client_id_hint}</code>
                    </p>
                ) : (
                    <p className="text-brand-muted">
                        No keys yet. Add <code className="font-mono text-brand-text">GOOGLE_CLIENT_ID</code> and{' '}
                        <code className="font-mono text-brand-text">GOOGLE_CLIENT_SECRET</code> to <code className="font-mono text-brand-text">.env</code>, then
                        run <code className="font-mono text-brand-text">php artisan config:clear</code>.
                    </p>
                )}
            </div>

            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">Authorised redirect URI</p>
                <div className="mt-1 flex items-center gap-1 rounded-lg border border-brand-border bg-brand-card py-1 pl-3 pr-1">
                    <code className="min-w-0 flex-1 select-all truncate font-mono text-xs text-brand-text" title={google.redirect_uri}>
                        {google.redirect_uri}
                    </code>
                    <CopyButton text={google.redirect_uri} label="Copy redirect URI" />
                </div>
                <p className="mt-1 text-xs text-brand-muted">Paste this exactly into Google Cloud Console → Clients → Authorised redirect URIs.</p>
            </div>
        </div>
    );
}

const HEX = /^#[0-9A-Fa-f]{6}$/;
const DEFAULT_NAV_BG = 'color-mix(in oklab, var(--color-brand-deep) 93%, var(--color-brand-accent))';

/** One colour: picker + hex box; empty = the default look. */
function ColorRow({ label, value, fallback, onChange, error }) {
    return (
        <div>
            <label className="flex items-center justify-between gap-3">
                <span className="text-sm text-brand-text">{label}</span>
                <span className="flex items-center gap-2">
                    <input
                        type="color"
                        value={HEX.test(value) ? value : fallback}
                        onChange={(e) => onChange(e.target.value.toUpperCase())}
                        aria-label={`${label} colour picker`}
                        className="h-9 w-9 cursor-pointer rounded-lg border border-brand-border bg-brand-card p-0.5"
                    />
                    <input
                        type="text"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        placeholder="Default"
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

/** Sidebar + mobile tab bar colours for every dashboard, with a live mini preview. */
function SidebarColors({ saved, errors }) {
    const [bg, setBg] = useState(saved?.bg ?? '');
    const [text, setText] = useState(saved?.text ?? '');
    const [saving, setSaving] = useState(false);
    const busy = { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) };
    const dirty = bg !== (saved?.bg ?? '') || text !== (saved?.text ?? '');

    const previewVars = { '--nav-bg': HEX.test(bg) ? bg : DEFAULT_NAV_BG, '--nav-text': HEX.test(text) ? text : '#ffffff' };

    return (
        <Panel title="Sidebar colours" description="Background and text of the dashboard menu, for the admin panel and owners on the standard look.">
            <div className="grid grid-cols-1 gap-6 md:grid-cols-[1fr_220px]">
                <div className="space-y-3">
                    <ColorRow label="Background" value={bg} fallback="#133049" onChange={setBg} error={errors?.bg} />
                    <ColorRow label="Text" value={text} fallback="#FFFFFF" onChange={setText} error={errors?.text} />
                    <div className="flex flex-wrap gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => router.put('/admin/settings/sidebar', { bg: bg || null, text: text || null }, busy)}
                            disabled={!dirty || saving}
                            className={primaryButton}
                        >
                            {saving ? 'Saving…' : 'Save colours'}
                        </button>
                        {saved && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.put('/admin/settings/sidebar', { bg: null, text: null }, { ...busy, onSuccess: () => (setBg(''), setText('')) })
                                }
                                disabled={saving}
                                className={secondaryButton}
                            >
                                <LuRotateCcw className="h-4 w-4" /> Reset to default
                            </button>
                        )}
                    </div>
                </div>

                {/* Same classes as the real sidebar, with the draft colours. */}
                <div style={previewVars} className={`space-y-1 rounded-xl p-3 ${navSurface}`} aria-hidden="true">
                    <p className="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-nav-text/40">Preview</p>
                    <span className={sideLinkClass(true)}>
                        <LuLayoutDashboard className="h-[18px] w-[18px]" /> Dashboard
                    </span>
                    <span className={sideLinkClass(false)}>
                        <LuStore className="h-[18px] w-[18px]" /> Shops
                    </span>
                    <span className={sideLinkClass(false)}>
                        <LuSettings className="h-[18px] w-[18px]" /> Settings
                    </span>
                </div>
            </div>
        </Panel>
    );
}

/** Where shops send bank transfers for orders we arrange (shown on their unpaid orders). */
function BankDetails({ saved }) {
    const form = useForm({
        account_name: saved?.account_name ?? '',
        bank_name: saved?.bank_name ?? '',
        sort_code: saved?.sort_code ?? '',
        account_number: saved?.account_number ?? '',
    });
    const field = (key, label, props = {}) => (
        <label className="block min-w-0">
            <span className="mb-1.5 block text-sm font-medium text-brand-text">{label}</span>
            <input
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
                className="w-full min-w-0 rounded-xl border border-brand-border bg-brand-card px-3.5 py-2.5 text-sm text-brand-text outline-none transition focus:border-brand-accent focus:ring-4 focus:ring-brand-accent/10"
                {...props}
            />
            <FieldError message={form.errors[key]} />
        </label>
    );

    return (
        <Panel
            title="Bank details for orders"
            description="Shops see these on an order you've arranged that's waiting for their bank transfer, with the order's reference (e.g. TADA-42)."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/admin/settings/bank', { preserveScroll: true });
                }}
                noValidate
                className="space-y-4"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {field('account_name', 'Account name', { placeholder: 'Techsa Ltd' })}
                    {field('bank_name', 'Bank (optional)', { placeholder: 'Barclays' })}
                    {field('sort_code', 'Sort code', { placeholder: '12-34-56', inputMode: 'numeric' })}
                    {field('account_number', 'Account number', { placeholder: '12345678', inputMode: 'numeric' })}
                </div>
                <button type="submit" disabled={form.processing} className={primaryButton}>
                    <LuLandmark className="h-4 w-4" /> {form.processing ? 'Saving…' : 'Save bank details'}
                </button>
            </form>
        </Panel>
    );
}

/** One tab per kind of setting; the open one is kept in the URL hash (#payments). */
const TABS = [
    { key: 'signin', label: 'Sign-in', icon: LuKeyRound, fields: ['enabled'] },
    { key: 'payments', label: 'Payments', icon: LuLandmark, fields: ['account_name', 'bank_name', 'sort_code', 'account_number'] },
    { key: 'appearance', label: 'Appearance', icon: LuPalette, fields: ['bg', 'text'] },
    { key: 'menu', label: 'Menu photos', icon: LuImage, fields: ['mode'] },
    { key: 'features', label: 'Features', icon: LuToggleRight, fields: ['feature', 'enabled'] },
];

/**
 * Owner features' platform defaults. Each shop follows these unless the
 * admin set it on / off on that shop's settings page (Features tab).
 */
function FeatureDefaults({ features, errors }) {
    const [saving, setSaving] = useState(null);

    function toggle(key, enabled) {
        router.put('/admin/settings/features', { feature: key, enabled }, {
            preserveScroll: true,
            onStart: () => setSaving(key),
            onFinish: () => setSaving(null),
        });
    }

    return (
        <Panel
            title="Features"
            description="What shops get by default. Override it for a single shop on its settings page (Features tab)."
            bodyClassName="divide-y divide-brand-border"
        >
            {features.map((f) => (
                <MethodRow
                    key={f.key}
                    icon={(() => {
                        const Icon = navIcon(f.key);
                        return <Icon className="h-5 w-5 text-brand-accent" />;
                    })()}
                    title={f.label}
                    description={f.description}
                    status={<StatusChip tone={f.default ? 'live' : 'off'}>{f.default ? 'On by default' : 'Off by default'}</StatusChip>}
                    control={<Switch checked={f.default} onChange={(on) => toggle(f.key, on)} disabled={saving !== null} />}
                >
                    {(f.forced_on > 0 || f.forced_off > 0) && (
                        <p className="text-xs text-brand-muted">
                            {[
                                f.forced_on > 0 && `Switched on for ${f.forced_on} ${f.forced_on === 1 ? 'shop' : 'shops'}`,
                                f.forced_off > 0 && `switched off for ${f.forced_off} ${f.forced_off === 1 ? 'shop' : 'shops'}`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}{' '}
                            on their own settings.
                        </p>
                    )}
                    {f.default && (
                        <p className="mt-1 text-xs text-brand-muted">Switching it off keeps it on for shops already using it.</p>
                    )}
                </MethodRow>
            ))}
            <div className="px-5">
                <FieldError message={errors?.feature ?? errors?.enabled} />
            </div>
        </Panel>
    );
}

/**
 * Who checks the catalog photos "Find photos" brings in for menu items:
 * Gemini, or the person in the menu editor ("Is this …?" yes / no).
 */
function MenuPhotos({ settings, error }) {
    const [saving, setSaving] = useState(false);
    const options = [
        {
            mode: 'ai',
            icon: LuSparkles,
            title: 'AI checks each photo',
            description: 'Gemini looks at each catalog photo and keeps the first that shows the item. Hands-off, uses Gemini credits.',
            disabled: !settings.ai_available,
            note: !settings.ai_available && 'Needs GEMINI_API_KEY in .env.',
        },
        {
            mode: 'manual',
            icon: LuHand,
            title: 'You confirm each photo',
            description: 'Whoever finds photos (you or the owner) is shown each one and asked "Is this …?" - yes keeps it, no shows the next. No Gemini cost.',
        },
    ];

    function choose(mode) {
        if (mode === settings.chosen && mode === settings.mode) return;
        router.put('/admin/settings/menu-photos', { mode }, { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) });
    }

    return (
        <Panel title="Menu item photos" description="How the photos “Find photos” brings in from the product catalog are checked before they go on a menu.">
            {!settings.catalog_configured && (
                <p className="mb-4 flex items-start gap-2 rounded-xl bg-amber-500/10 px-3.5 py-3 text-sm text-amber-800">
                    <LuTriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    The product catalog isn't set up (PRODUCT_API_* in .env), so photos can't be found either way yet.
                </p>
            )}
            <div className="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="How menu photos are checked">
                {options.map((o) => {
                    const active = settings.mode === o.mode;

                    return (
                        <button
                            key={o.mode}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            disabled={saving || o.disabled}
                            onClick={() => choose(o.mode)}
                            className={`flex items-start gap-3 rounded-2xl border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                                active ? 'border-brand-accent bg-brand-accent/5 ring-1 ring-brand-accent' : 'border-brand-border hover:border-brand-accent/60'
                            }`}
                        >
                            <o.icon className={`mt-0.5 h-5 w-5 shrink-0 ${active ? 'text-brand-accent' : 'text-brand-muted'}`} />
                            <span className="min-w-0">
                                <span className="block font-semibold text-brand-text">{o.title}</span>
                                <span className="mt-1 block text-sm text-brand-muted">{o.description}</span>
                                {o.note && <span className="mt-1.5 block text-xs font-medium text-amber-800">{o.note}</span>}
                            </span>
                        </button>
                    );
                })}
            </div>
            {settings.chosen === 'ai' && settings.mode === 'manual' && (
                <p className="mt-3 text-xs text-brand-muted">AI is chosen but there's no Gemini key, so photos are confirmed by hand for now.</p>
            )}
            <FieldError message={error} />
        </Panel>
    );
}

function initialTab() {
    const hash = typeof window !== 'undefined' ? window.location.hash.slice(1) : '';
    return TABS.some((t) => t.key === hash) ? hash : 'signin';
}

export default function Settings({ google, sidebar, bank, menuPhotos, features }) {
    const { errors } = usePage().props;
    const [tab, setTab] = useState(initialTab);
    const [saving, setSaving] = useState(false);
    const tabHasError = (t) => t.fields.some((field) => errors?.[field]);

    function openTab(key) {
        setTab(key);
        window.history.replaceState(window.history.state, '', `#${key}`);
    }

    // After a failed save, show the tab with the problem on it.
    useEffect(() => {
        if (TABS.find((t) => t.key === tab && tabHasError(t))) return;
        const withError = TABS.find(tabHasError);
        if (withError) openTab(withError.key);
    }, [errors]);
    const live = google.enabled && google.configured;
    const [confirm, confirmDialog] = useConfirm();

    async function toggleGoogle(next) {
        if (!next && google.google_only_owners > 0) {
            const n = google.google_only_owners;
            const ok = await confirm({
                title: 'Turn off Google sign-in?',
                message: `${n} ${n === 1 ? 'owner signs' : 'owners sign'} in only with Google and won’t be able to log in while it’s off.`,
                confirmLabel: 'Turn it off',
                danger: true,
            });
            if (!ok) return;
        }

        router.put('/admin/settings/google', { enabled: next }, { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) });
    }

    const googleStatus = !google.configured ? (
        <StatusChip tone="warn">Keys missing</StatusChip>
    ) : live ? (
        <StatusChip tone="live">Live</StatusChip>
    ) : (
        <StatusChip tone="off">Off</StatusChip>
    );

    return (
        <AdminLayout title="Settings" description="App-wide options. API keys stay in .env, never here.">
            <nav
                role="tablist"
                aria-label="Settings"
                className="-mx-1 mb-4 flex gap-1 overflow-x-auto border-b border-brand-border px-1 [scrollbar-width:none]"
            >
                {TABS.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
                        onClick={() => openTab(t.key)}
                        className={`-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                            tab === t.key ? 'border-brand-accent text-brand-text' : 'border-transparent text-brand-muted hover:text-brand-text'
                        }`}
                    >
                        <t.icon className="h-4 w-4" />
                        {t.label}
                        {tabHasError(t) && <span className="h-1.5 w-1.5 rounded-full bg-red-600" aria-label="has errors" />}
                    </button>
                ))}
            </nav>

            {tab === 'signin' && (
                <Panel
                    title="Sign-in methods"
                    description="How shop owners sign up and log in. Customers never need an account."
                    bodyClassName="divide-y divide-brand-border"
                >
                    <MethodRow
                        icon={<LuMail className="h-5 w-5 text-brand-accent" />}
                        title="Email & password"
                        description="Always available, and the only way admins log in."
                        status={<StatusChip tone="live">Always on</StatusChip>}
                    />

                    <MethodRow
                        icon={<FcGoogle className="h-5 w-5" />}
                        title="Google"
                        description={'"Continue with Google" on the sign-up and log-in pages.'}
                        status={googleStatus}
                        control={<Switch checked={live} onChange={toggleGoogle} disabled={saving || !google.configured} />}
                    >
                        <FieldError message={errors?.enabled} />

                        {live && google.google_only_owners > 0 && (
                            <div className="mb-3 flex items-start gap-2.5 rounded-xl bg-amber-500/10 px-3.5 py-3 text-sm text-amber-800">
                                <LuTriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                                <p>
                                    {google.google_only_owners} {google.google_only_owners === 1 ? 'owner signs' : 'owners sign'} in only with Google. Turning
                                    this off locks them out until it's back on.
                                </p>
                            </div>
                        )}

                        <GoogleSetup google={google} />
                    </MethodRow>
                </Panel>
            )}
            {tab === 'payments' && <BankDetails saved={bank} />}
            {tab === 'appearance' && <SidebarColors saved={sidebar} errors={errors} />}
            {tab === 'menu' && <MenuPhotos settings={menuPhotos} error={errors?.mode} />}
            {tab === 'features' && <FeatureDefaults features={features} errors={errors} />}
            {confirmDialog}
        </AdminLayout>
    );
}
