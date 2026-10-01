import { useForm } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { LuDownload, LuExternalLink } from 'react-icons/lu';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { MAX_STAMPS, MIN_STAMPS } from '@/Components/Dashboard/ShopFields';
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';

function Field({ label, error, hint, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-medium text-brand-text">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-brand-muted">{hint}</span>}
            <FieldError message={error} />
        </label>
    );
}

/** Business contact + location (collected at shop setup). Its own form and save button. */
function ContactPanel({ contact }) {
    const form = useForm({
        contact_name: contact.contact_name ?? '',
        contact_email: contact.contact_email ?? '',
        contact_phone: contact.contact_phone ?? '',
        address_line1: contact.address_line1 ?? '',
        address_line2: contact.address_line2 ?? '',
        town: contact.town ?? '',
        postcode: contact.postcode ?? '',
        delivery_same: !contact.delivery_address,
        delivery_address: contact.delivery_address ?? '',
    });

    const text = (key) => ({ value: form.data[key], onChange: (e) => form.setData(key, e.target.value), className: inputClass });
    const missing = !contact.contact_phone || !contact.town;

    function submit(e) {
        e.preventDefault();
        form.put('/dashboard/settings/contact', { preserveScroll: true });
    }

    return (
        <Panel className="lg:col-span-2" title="Contact & address" description="How we reach you, and where your shop is.">
            {missing && (
                <p className="mb-5 rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-800">Please add your contact number and shop address - we don't have them yet.</p>
            )}
            <form onSubmit={submit} noValidate className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label="Contact person name" error={form.errors.contact_name}>
                        <input type="text" autoComplete="name" {...text('contact_name')} />
                    </Field>
                    <Field label="Contact number" error={form.errors.contact_phone} hint="Mobile or landline.">
                        <input type="tel" inputMode="tel" autoComplete="tel" {...text('contact_phone')} />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Email" error={form.errors.contact_email}>
                            <input type="email" autoComplete="email" {...text('contact_email')} />
                        </Field>
                    </div>
                </div>

                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Shop address</h3>
                    <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Address line 1" error={form.errors.address_line1}>
                            <input type="text" autoComplete="address-line1" {...text('address_line1')} />
                        </Field>
                        <Field label="Address line 2 (optional)" error={form.errors.address_line2}>
                            <input type="text" autoComplete="address-line2" {...text('address_line2')} />
                        </Field>
                        <Field label="Town / city" error={form.errors.town}>
                            <input type="text" autoComplete="address-level2" {...text('town')} />
                        </Field>
                        <Field label="Postcode" error={form.errors.postcode}>
                            <input
                                type="text"
                                autoComplete="postal-code"
                                autoCapitalize="characters"
                                {...text('postcode')}
                                onChange={(e) => form.setData('postcode', e.target.value.toUpperCase())}
                            />
                        </Field>
                    </div>
                </div>

                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Delivery address (optional)</h3>
                    <label className="mt-3 flex cursor-pointer items-center gap-2.5 text-sm text-brand-text">
                        <input
                            type="checkbox"
                            checked={form.data.delivery_same}
                            onChange={(e) => form.setData('delivery_same', e.target.checked)}
                            className="h-4 w-4 accent-[var(--color-brand-accent)]"
                        />
                        Same as the shop address
                    </label>
                    {!form.data.delivery_same && (
                        <div className="mt-3">
                            <textarea
                                rows={3}
                                value={form.data.delivery_address}
                                onChange={(e) => form.setData('delivery_address', e.target.value)}
                                autoComplete="shipping street-address"
                                aria-label="Delivery address"
                                className={`${inputClass} resize-y`}
                            />
                            <FieldError message={form.errors.delivery_address} />
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-3 border-t border-brand-border pt-5">
                    <button type="submit" disabled={form.processing} className={primaryButton}>
                        {form.processing ? 'Saving…' : 'Save contact details'}
                    </button>
                    {form.recentlySuccessful && <span className="text-sm text-green-700">Saved</span>}
                </div>
            </form>
        </Panel>
    );
}

export default function Settings({ shop, contact }) {
    const [posterQr, setPosterQr] = useState(null);
    const cardUrl = typeof window !== 'undefined' ? `${window.location.origin}/s/${shop.slug}` : `/s/${shop.slug}`;

    const form = useForm({
        name: shop.name,
        max_stamps: shop.max_stamps,
        reward_title: shop.reward_title,
        google_review_url: shop.google_review_url ?? '',
        instagram_url: shop.instagram_url ?? '',
        wifi_ssid: shop.wifi_ssid ?? '',
        wifi_password: shop.wifi_password ?? '',
    });

    useEffect(() => {
        QRCode.toDataURL(cardUrl, { margin: 2, width: 600 })
            .then(setPosterQr)
            .catch(() => setPosterQr(null));
    }, [cardUrl]);

    function submit(e) {
        e.preventDefault();
        form.put('/dashboard/settings', { preserveScroll: true });
    }

    const text = (key) => ({
        value: form.data[key],
        onChange: (e) => form.setData(key, e.target.value),
        className: inputClass,
    });

    return (
        <OwnerLayout shop={shop} title="Settings" description="Your loyalty card, links and the counter QR code.">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Panel className="lg:col-span-2" title="Shop settings">
                    <form onSubmit={submit} noValidate className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Shop name" error={form.errors.name}>
                                <input type="text" {...text('name')} />
                            </Field>
                            <Field label="Stamps for a reward" error={form.errors.max_stamps} hint={`Between ${MIN_STAMPS} and ${MAX_STAMPS}.`}>
                                <input
                                    type="number"
                                    min={MIN_STAMPS}
                                    max={MAX_STAMPS}
                                    value={form.data.max_stamps}
                                    onChange={(e) => form.setData('max_stamps', Number(e.target.value))}
                                    className={inputClass}
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label="Reward" error={form.errors.reward_title} hint="Shown on every customer's card.">
                                    <input type="text" {...text('reward_title')} />
                                </Field>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Links</h3>
                            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Instagram URL" error={form.errors.instagram_url}>
                                    <input type="url" placeholder="https://instagram.com/…" {...text('instagram_url')} />
                                </Field>
                                <Field label="Google review URL" error={form.errors.google_review_url} hint="After rating you, customers get a button to post it on Google too.">
                                    <input id="google-review-url" type="url" placeholder="https://g.page/r/…" {...text('google_review_url')} />
                                </Field>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Guest Wi-Fi</h3>
                            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Network name" error={form.errors.wifi_ssid}>
                                    <input type="text" {...text('wifi_ssid')} />
                                </Field>
                                <Field label="Password" error={form.errors.wifi_password}>
                                    <input type="text" {...text('wifi_password')} />
                                </Field>
                            </div>
                        </div>

                        <div className="flex items-center gap-3 border-t border-brand-border pt-5">
                            <button type="submit" disabled={form.processing} className={primaryButton}>
                                {form.processing ? 'Saving…' : 'Save changes'}
                            </button>
                            {form.recentlySuccessful && <span className="text-sm text-green-700">Saved</span>}
                        </div>
                    </form>
                </Panel>

                <Panel title="Counter QR code" description="Print it and put it by the till. Customers scan it to get their card.">
                    <div className="text-center">
                        {posterQr && (
                            <img
                                src={posterQr}
                                alt="Customer card QR code"
                                className="mx-auto h-52 w-52 rounded-xl border border-brand-border bg-white p-2"
                            />
                        )}
                        <p className="mt-3 break-all text-xs text-brand-muted">{cardUrl}</p>
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            {posterQr && (
                                <a href={posterQr} download={`${shop.slug}-qr.png`} className={primaryButton}>
                                    <LuDownload className="h-4 w-4" /> Download
                                </a>
                            )}
                            <a href={`/s/${shop.slug}`} target="_blank" rel="noopener noreferrer" className={secondaryButton}>
                                <LuExternalLink className="h-4 w-4" /> Open
                            </a>
                        </div>
                    </div>
                </Panel>

                {/* Below the shop settings (the QR sits beside them in the first row). */}
                <ContactPanel contact={contact} />
            </div>
        </OwnerLayout>
    );
}
