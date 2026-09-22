import { Head, router, useForm, usePage } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import AppHeader from '@/Components/AppHeader';

function Field({ label, value, onChange, error, type = 'text', placeholder }) {
    return (
        <div>
            <label className="block text-xs font-medium text-brand-muted">{label}</label>
            <input
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function StatCard({ label, value }) {
    return (
        <div className="rounded-brand border border-brand-border bg-brand-card p-4 text-center">
            <p className="text-2xl font-bold text-brand-text">{value}</p>
            <p className="mt-1 text-xs text-brand-muted">{label}</p>
        </div>
    );
}

export default function Index({ shop, stats, staffDevices }) {
    const { flash } = usePage().props;
    const [posterQr, setPosterQr] = useState(null);
    const [staffQr, setStaffQr] = useState(null);

    const settingsForm = useForm({
        name: shop.name,
        max_stamps: shop.max_stamps,
        reward_title: shop.reward_title,
        google_review_url: shop.google_review_url ?? '',
        instagram_url: shop.instagram_url ?? '',
        wifi_ssid: shop.wifi_ssid ?? '',
        wifi_password: shop.wifi_password ?? '',
    });

    const deviceForm = useForm({ name: '' });

    useEffect(() => {
        const url = `${window.location.origin}/s/${shop.slug}`;
        QRCode.toDataURL(url, { margin: 1, width: 260 })
            .then(setPosterQr)
            .catch(() => setPosterQr(null));
    }, [shop.slug]);

    useEffect(() => {
        if (!flash?.staffToken) {
            setStaffQr(null);
            return;
        }

        // /staff/setup/{token} is the Stage 5 onboarding page - not built
        // yet, but the token/QR generation itself is this stage's job.
        const url = `${window.location.origin}/staff/setup/${flash.staffToken}`;
        QRCode.toDataURL(url, { margin: 1, width: 220 })
            .then(setStaffQr)
            .catch(() => setStaffQr(null));
    }, [flash?.staffToken]);

    function submitSettings(e) {
        e.preventDefault();
        settingsForm.put('/dashboard/settings');
    }

    function submitDevice(e) {
        e.preventDefault();
        deviceForm.post('/dashboard/staff-devices', { onSuccess: () => deviceForm.reset() });
    }

    function revokeDevice(id) {
        router.delete(`/dashboard/staff-devices/${id}`);
    }

    return (
        <>
            <Head title={shop.name} />
            <div className="min-h-screen bg-brand-bg pb-16">
                <AppHeader title={shop.name} />

                <div className="mx-auto max-w-2xl space-y-6 px-5 py-8">
                    <div className="grid grid-cols-3 gap-3">
                        <StatCard label="Customers" value={stats.customer_count} />
                        <StatCard label="Stamps today" value={stats.stamps_today} />
                        <StatCard label="Rewards redeemed" value={stats.rewards_redeemed} />
                    </div>

                    <section className="rounded-brand border border-brand-border bg-brand-card p-5">
                        <h2 className="font-heading text-lg font-bold text-brand-text">Settings</h2>

                        <form onSubmit={submitSettings} noValidate className="mt-4 space-y-3">
                            <Field
                                label="Shop name"
                                value={settingsForm.data.name}
                                onChange={(v) => settingsForm.setData('name', v)}
                                error={settingsForm.errors.name}
                            />
                            <div>
                                <label className="block text-xs font-medium text-brand-muted">Stamps needed (4–12)</label>
                                <input
                                    type="number"
                                    min={4}
                                    max={12}
                                    value={settingsForm.data.max_stamps}
                                    onChange={(e) => settingsForm.setData('max_stamps', Number(e.target.value))}
                                    className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                                />
                                {settingsForm.errors.max_stamps && <p className="mt-1 text-xs text-red-600">{settingsForm.errors.max_stamps}</p>}
                            </div>
                            <Field
                                label="Reward title"
                                value={settingsForm.data.reward_title}
                                onChange={(v) => settingsForm.setData('reward_title', v)}
                                error={settingsForm.errors.reward_title}
                            />
                            <Field
                                label="Google review URL"
                                value={settingsForm.data.google_review_url}
                                onChange={(v) => settingsForm.setData('google_review_url', v)}
                                error={settingsForm.errors.google_review_url}
                                placeholder="https://g.page/r/…"
                            />
                            <Field
                                label="Instagram URL"
                                value={settingsForm.data.instagram_url}
                                onChange={(v) => settingsForm.setData('instagram_url', v)}
                                error={settingsForm.errors.instagram_url}
                                placeholder="https://instagram.com/…"
                            />
                            <Field
                                label="Wi-Fi network name"
                                value={settingsForm.data.wifi_ssid}
                                onChange={(v) => settingsForm.setData('wifi_ssid', v)}
                                error={settingsForm.errors.wifi_ssid}
                            />
                            <Field
                                label="Wi-Fi password"
                                value={settingsForm.data.wifi_password}
                                onChange={(v) => settingsForm.setData('wifi_password', v)}
                                error={settingsForm.errors.wifi_password}
                            />

                            <button
                                type="submit"
                                disabled={settingsForm.processing}
                                className="rounded-brand bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text disabled:opacity-50"
                            >
                                {settingsForm.processing ? 'Saving…' : settingsForm.recentlySuccessful ? 'Saved!' : 'Save settings'}
                            </button>
                        </form>
                    </section>

                    <section className="rounded-brand border border-brand-border bg-brand-card p-5 text-center">
                        <h2 className="font-heading text-lg font-bold text-brand-text">Customer QR poster</h2>
                        <p className="mt-1 text-sm text-brand-muted">Print this and put it on the counter.</p>
                        {posterQr && <img src={posterQr} alt="Customer card QR code" className="mx-auto mt-4 h-52 w-52 rounded border border-brand-border" />}
                        <p className="mt-2 text-xs text-brand-muted">/s/{shop.slug}</p>
                    </section>

                    <section className="rounded-brand border border-brand-border bg-brand-card p-5">
                        <h2 className="font-heading text-lg font-bold text-brand-text">Staff devices</h2>
                        <p className="mt-1 text-sm text-brand-muted">Each staff phone needs its own device to scan customer cards.</p>

                        {flash?.staffToken && (
                            <div className="mt-4 rounded-brand border border-brand-accent bg-brand-accent/10 p-4 text-center text-sm text-brand-text">
                                <p className="font-semibold">Device added — onboard it now, this won't be shown again.</p>
                                {staffQr && <img src={staffQr} alt="Staff device onboarding QR code" className="mx-auto mt-3 h-40 w-40 rounded border border-brand-border" />}
                                <p className="mt-2 break-all font-mono text-xs">/staff/setup/{flash.staffToken}</p>
                            </div>
                        )}

                        <form onSubmit={submitDevice} noValidate className="mt-4 flex gap-2">
                            <input
                                type="text"
                                value={deviceForm.data.name}
                                onChange={(e) => deviceForm.setData('name', e.target.value)}
                                placeholder="e.g. Counter iPad"
                                className="min-w-0 flex-1 rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                            />
                            <button
                                type="submit"
                                disabled={deviceForm.processing}
                                className="shrink-0 rounded-brand bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text disabled:opacity-50"
                            >
                                Add device
                            </button>
                        </form>
                        {deviceForm.errors.name && <p className="mt-1 text-xs text-red-600">{deviceForm.errors.name}</p>}

                        <div className="mt-4 space-y-2">
                            {staffDevices.length === 0 && <p className="text-sm text-brand-muted">No devices yet.</p>}

                            {staffDevices.map((device) => (
                                <div key={device.id} className="flex items-center justify-between rounded-brand border border-brand-border px-4 py-3">
                                    <div>
                                        <p className="text-sm font-medium text-brand-text">{device.name}</p>
                                        <p className="text-xs text-brand-muted">
                                            {device.revoked
                                                ? 'Revoked'
                                                : device.last_used_at
                                                  ? `Last used ${device.last_used_at}`
                                                  : `Added ${device.created_at}, not used yet`}
                                        </p>
                                    </div>
                                    {!device.revoked && (
                                        <button onClick={() => revokeDevice(device.id)} className="text-xs font-semibold text-red-600">
                                            Revoke
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
