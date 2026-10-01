import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import { StampStepper } from '@/Components/Dashboard/ShopFields';
import { FieldError, inputClass, Panel, primaryButton, secondaryButton, Switch } from '@/Components/Dashboard/Ui';

function Field({ label, error, hint, children }) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 block text-sm font-medium text-brand-text">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-brand-muted">{hint}</span>}
            <FieldError message={error} />
        </label>
    );
}

export default function ShopSettings({ shop }) {
    const form = useForm({
        name: shop.name ?? '',
        max_stamps: shop.max_stamps,
        reward_title: shop.reward_title ?? '',
        google_review_url: shop.google_review_url ?? '',
        google_review_direct: Boolean(shop.google_review_direct),
        instagram_url: shop.instagram_url ?? '',
        wifi_ssid: shop.wifi_ssid ?? '',
        wifi_password: shop.wifi_password ?? '',
        contact_name: shop.contact_name ?? '',
        contact_email: shop.contact_email ?? '',
        contact_phone: shop.contact_phone ?? '',
        address_line1: shop.address_line1 ?? '',
        address_line2: shop.address_line2 ?? '',
        town: shop.town ?? '',
        postcode: shop.postcode ?? '',
        delivery_address: shop.delivery_address ?? '',
    });

    const text = (key) => ({
        value: form.data[key],
        onChange: (event) => form.setData(key, event.target.value),
        className: inputClass,
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/admin/shops/${shop.id}/settings`, { preserveScroll: true });
    }

    return (
        <AdminLayout
            title="Shop settings"
            description={`Configure ${shop.name}'s loyalty card, customer links, Wi-Fi and business contact details.`}
            actions={<Link href="/admin" className={secondaryButton}>Back to shops</Link>}
        >
            <form onSubmit={submit} noValidate className="space-y-4">
                <Panel title="Loyalty card" description="The details customers see on their card.">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Shop name" error={form.errors.name}>
                            <input type="text" {...text('name')} />
                        </Field>
                        <Field label="Shop link" hint="The shop URL slug cannot be changed here.">
                            <input value={`/s/${shop.slug}`} readOnly className={`${inputClass} bg-brand-bg text-brand-muted`} />
                        </Field>
                        <Field label="Stamps for a reward" error={form.errors.max_stamps}>
                            <StampStepper id="admin-shop-max-stamps" value={form.data.max_stamps} onChange={(value) => form.setData('max_stamps', value)} />
                        </Field>
                        <Field label="Reward" error={form.errors.reward_title}>
                            <input type="text" {...text('reward_title')} />
                        </Field>
                    </div>
                </Panel>

                <Panel title="Customer links" description="Choose what customers can open from their card.">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Instagram URL" error={form.errors.instagram_url}>
                            <input type="url" placeholder="https://instagram.com/…" {...text('instagram_url')} />
                        </Field>
                        <Field label="Google review URL" error={form.errors.google_review_url} hint="Required to enable direct Google Reviews.">
                            <input type="url" placeholder="https://g.page/r/…" {...text('google_review_url')} />
                        </Field>
                        <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5 sm:col-span-2">
                            <Switch
                                checked={form.data.google_review_direct}
                                disabled={!form.data.google_review_url && !form.data.google_review_direct}
                                onChange={(enabled) => form.setData('google_review_direct', enabled)}
                                label="Go directly to Google Reviews"
                                description={form.data.google_review_direct
                                    ? 'The customer card action opens Google directly; in-app feedback is skipped.'
                                    : 'Customers leave feedback in-app first, then can optionally share it on Google.'}
                            />
                        </div>
                    </div>
                </Panel>

                <Panel title="Guest Wi-Fi" description="Optional network details shown to customers.">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Network name" error={form.errors.wifi_ssid}>
                            <input type="text" {...text('wifi_ssid')} />
                        </Field>
                        <Field label="Password" error={form.errors.wifi_password}>
                            <input type="text" {...text('wifi_password')} />
                        </Field>
                    </div>
                </Panel>

                <Panel title="Business contact & location" description="Optional contact and address details collected for this shop.">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Contact person" error={form.errors.contact_name}>
                            <input type="text" {...text('contact_name')} />
                        </Field>
                        <Field label="Contact email" error={form.errors.contact_email}>
                            <input type="email" {...text('contact_email')} />
                        </Field>
                        <Field label="Contact phone" error={form.errors.contact_phone} hint="Use international format, e.g. +442079460000.">
                            <input type="tel" {...text('contact_phone')} />
                        </Field>
                        <Field label="Town / city" error={form.errors.town}>
                            <input type="text" {...text('town')} />
                        </Field>
                        <Field label="Address line 1" error={form.errors.address_line1}>
                            <input type="text" {...text('address_line1')} />
                        </Field>
                        <Field label="Address line 2" error={form.errors.address_line2}>
                            <input type="text" {...text('address_line2')} />
                        </Field>
                        <Field label="Postcode / PIN code" error={form.errors.postcode}>
                            <input type="text" autoCapitalize="characters" {...text('postcode')} />
                        </Field>
                        <Field label="Separate delivery address" error={form.errors.delivery_address} hint="Leave blank if the delivery address is the shop address.">
                            <input type="text" {...text('delivery_address')} />
                        </Field>
                    </div>
                </Panel>

                <div className="flex flex-wrap items-center gap-3">
                    <button type="submit" disabled={form.processing} className={primaryButton}>
                        {form.processing ? 'Saving…' : 'Save shop settings'}
                    </button>
                    <Link href="/admin" className={secondaryButton}>Cancel</Link>
                    {form.recentlySuccessful && <span className="text-sm text-green-700">Saved</span>}
                </div>
            </form>
        </AdminLayout>
    );
}
