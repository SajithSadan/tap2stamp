import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FcGoogle } from 'react-icons/fc';
import { LuCircleAlert, LuKeyRound, LuLayoutDashboard, LuMail, LuRotateCcw, LuSettings, LuStore, LuTriangleAlert } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
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
                        <code className="font-mono text-brand-text">GOOGLE_CLIENT_SECRET</code> to <code className="font-mono text-brand-text">.env</code>,
                        then run <code className="font-mono text-brand-text">php artisan config:clear</code>.
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
                                onClick={() => router.put('/admin/settings/sidebar', { bg: null, text: null }, { ...busy, onSuccess: () => (setBg(''), setText('')) })}
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

export default function Settings({ google, sidebar }) {
    const { errors } = usePage().props;
    const [saving, setSaving] = useState(false);
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
            <Panel title="Sign-in methods" description="How shop owners sign up and log in. Customers never need an account." bodyClassName="divide-y divide-brand-border">
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
                    control={
                        <Switch
                            checked={live}
                            onChange={toggleGoogle}
                            disabled={saving || !google.configured}
                        />
                    }
                >
                    <FieldError message={errors?.enabled} />

                    {live && google.google_only_owners > 0 && (
                        <div className="mb-3 flex items-start gap-2.5 rounded-xl bg-amber-500/10 px-3.5 py-3 text-sm text-amber-800">
                            <LuTriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                {google.google_only_owners} {google.google_only_owners === 1 ? 'owner signs' : 'owners sign'} in only with Google. Turning this off
                                locks them out until it's back on.
                            </p>
                        </div>
                    )}

                    <GoogleSetup google={google} />
                </MethodRow>
            </Panel>
            <div className="mt-6">
                <SidebarColors saved={sidebar} errors={errors} />
            </div>
            {confirmDialog}
        </AdminLayout>
    );
}
