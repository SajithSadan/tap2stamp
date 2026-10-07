import { Link, useForm, usePage } from "@inertiajs/react";
import QRCode from "qrcode";
import { useEffect, useState } from "react";
import { LuDownload, LuExternalLink, LuNfc, LuQrCode } from "react-icons/lu";
import AddressLookup from "@/Components/AddressLookup";
import CountrySelect from "@/Components/CountrySelect";
import PhoneField from "@/Components/PhoneField";
import { dialCodeOf, INDIA, phonePlaceholder, UK } from "@/lib/validation";
import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import { MAX_STAMPS, MIN_STAMPS } from "@/Components/Dashboard/ShopFields";
import {
    FieldError,
    inputClass,
    Panel,
    primaryButton,
    secondaryButton,
    Switch,
} from "@/Components/Dashboard/Ui";

function Field({ label, error, hint, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-medium text-brand-text">
                {label}
            </span>
            {children}
            {hint && !error && (
                <span className="mt-1 block text-xs text-brand-muted">
                    {hint}
                </span>
            )}
            <FieldError message={error} />
        </label>
    );
}

/** Business contact + location (collected at shop setup). Its own form and save button. */
function ContactPanel({ contact, countries }) {
    const loginEmail = usePage().props.auth?.user?.email;
    const form = useForm({
        contact_name: contact.contact_name ?? "",
        contact_email: contact.contact_email ?? "",
        contact_phone_code: contact.contact_phone_code ?? dialCodeOf(contact.country ?? UK, countries),
        contact_phone: contact.contact_phone ?? "",
        address_line1: contact.address_line1 ?? "",
        address_line2: contact.address_line2 ?? "",
        town: contact.town ?? "",
        postcode: contact.postcode ?? "",
        country: contact.country ?? UK,
        delivery_same: !contact.delivery_address,
        delivery_address: contact.delivery_address ?? "",
    });
    const inUk = form.data.country === UK;

    const text = (key) => ({
        value: form.data[key],
        onChange: (e) => form.setData(key, e.target.value),
        className: inputClass,
    });
    const missing = !contact.contact_phone || !contact.town;

    function submit(e) {
        e.preventDefault();
        form.put("/dashboard/settings/contact", { preserveScroll: true });
    }

    return (
        <Panel
            id="contact"
            className="lg:col-span-2"
            title="Contact & address"
            description="How we reach you, and where your shop is."
        >
            {missing && (
                <p className="mb-5 rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-800">
                    Please add your contact number and shop address - we don't
                    have them yet.
                </p>
            )}
            <form onSubmit={submit} noValidate className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field
                        label="Contact person name"
                        error={form.errors.contact_name}
                    >
                        <input
                            type="text"
                            autoComplete="name"
                            {...text("contact_name")}
                        />
                    </Field>
                    <div>
                        <Field label="Email" error={form.errors.contact_email}>
                            <input
                                type="email"
                                autoComplete="email"
                                {...text("contact_email")}
                            />
                        </Field>
                        {loginEmail &&
                            !form.errors.contact_email &&
                            (form.data.contact_email.trim().toLowerCase() ===
                            loginEmail.toLowerCase() ? (
                                <p className="mt-1 text-xs text-brand-muted">
                                    Same as your login email.
                                </p>
                            ) : (
                                <p className="mt-1 text-xs text-brand-muted">
                                    Your login: {loginEmail} ·{" "}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            form.setData(
                                                "contact_email",
                                                loginEmail,
                                            )
                                        }
                                        className="font-semibold text-brand-accent hover:underline"
                                    >
                                        Use this
                                    </button>
                                </p>
                            ))}
                    </div>
                    <div className="sm:col-span-2">
                        <Field
                            label="Contact number"
                            error={form.errors.contact_phone ?? form.errors.contact_phone_code}
                            hint="Mobile or landline."
                        >
                            <PhoneField
                                code={form.data.contact_phone_code}
                                number={form.data.contact_phone}
                                onCodeChange={(code) => form.setData("contact_phone_code", code)}
                                onNumberChange={(value) => form.setData("contact_phone", value)}
                                countries={countries}
                                country={form.data.country}
                                placeholder={phonePlaceholder(form.data.contact_phone_code)}
                                inputClassName={inputClass}
                            />
                        </Field>
                    </div>
                </div>

                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">
                        Shop address
                    </h3>
                    <div className="mt-3 max-w-sm">
                        <Field label="Country" error={form.errors.country}>
                            <CountrySelect
                                value={form.data.country}
                                onChange={(code) =>
                                    // The phone's country code follows, unless one was picked by hand.
                                    form.setData((data) => ({
                                        ...data,
                                        country: code,
                                        ...(data.contact_phone_code === dialCodeOf(data.country, countries) && {
                                            contact_phone_code: dialCodeOf(code, countries),
                                        }),
                                    }))
                                }
                                countries={countries}
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    {/* The address finder only knows UK addresses. */}
                    {inUk && (
                        <div className="mt-3">
                            <AddressLookup
                                initialPostcode={form.data.postcode}
                                onFound={(address) =>
                                    form.setData((data) => ({ ...data, ...address }))
                                }
                                inputClassName={inputClass}
                                buttonClassName={secondaryButton}
                            />
                        </div>
                    )}
                    <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field
                            label="Address line 1"
                            error={form.errors.address_line1}
                        >
                            <input
                                type="text"
                                autoComplete="address-line1"
                                {...text("address_line1")}
                            />
                        </Field>
                        <Field
                            label="Address line 2 (optional)"
                            error={form.errors.address_line2}
                        >
                            <input
                                type="text"
                                autoComplete="address-line2"
                                {...text("address_line2")}
                            />
                        </Field>
                        <Field label="Town / city" error={form.errors.town}>
                            <input
                                type="text"
                                autoComplete="address-level2"
                                {...text("town")}
                            />
                        </Field>
                        <Field
                            label={inUk ? "Postcode" : form.data.country === INDIA ? "PIN code" : "Postcode (optional)"}
                            error={form.errors.postcode}
                        >
                            <input
                                type="text"
                                autoComplete="postal-code"
                                autoCapitalize="characters"
                                {...text("postcode")}
                                onChange={(e) =>
                                    form.setData(
                                        "postcode",
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                    </div>
                </div>

                {/* We only post hardware to UK shops. */}
                {inUk && (
                <div>
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">
                        Delivery address (optional)
                    </h3>
                    <label className="mt-3 flex cursor-pointer items-center gap-2.5 text-sm text-brand-text">
                        <input
                            type="checkbox"
                            checked={form.data.delivery_same}
                            onChange={(e) =>
                                form.setData("delivery_same", e.target.checked)
                            }
                            className="h-4 w-4 accent-[var(--color-brand-accent)]"
                        />
                        Same as the shop address
                    </label>
                    {!form.data.delivery_same && (
                        <div className="mt-3">
                            <textarea
                                rows={3}
                                value={form.data.delivery_address}
                                onChange={(e) =>
                                    form.setData(
                                        "delivery_address",
                                        e.target.value,
                                    )
                                }
                                autoComplete="shipping street-address"
                                aria-label="Delivery address"
                                className={`${inputClass} resize-y`}
                            />
                            <FieldError
                                message={form.errors.delivery_address}
                            />
                        </div>
                    )}
                </div>
                )}

                <div className="flex items-center gap-3 border-t border-brand-border pt-5">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className={primaryButton}
                    >
                        {form.processing ? "Saving…" : "Save contact details"}
                    </button>
                    {form.recentlySuccessful && (
                        <span className="text-sm text-green-700">Saved</span>
                    )}
                </div>
            </form>
        </Panel>
    );
}

export default function Settings({ shop, contact, countries }) {
    const [posterQr, setPosterQr] = useState(null);
    // Only sent when the admin lets this shop see its card link (show_card_link).
    const cardUrl = shop.slug ? `${window.location.origin}/s/${shop.slug}` : null;

    const form = useForm({
        name: shop.name,
        max_stamps: shop.max_stamps,
        reward_title: shop.reward_title,
        google_review_url: shop.google_review_url ?? "",
        google_review_direct: Boolean(shop.google_review_direct),
        instagram_url: shop.instagram_url ?? "",
        wifi_ssid: shop.wifi_ssid ?? "",
        wifi_password: shop.wifi_password ?? "",
    });

    useEffect(() => {
        if (!cardUrl) return;
        QRCode.toDataURL(cardUrl, { margin: 2, width: 600 })
            .then(setPosterQr)
            .catch(() => setPosterQr(null));
    }, [cardUrl]);

    function submit(e) {
        e.preventDefault();
        form.put("/dashboard/settings", { preserveScroll: true });
    }

    const text = (key) => ({
        value: form.data[key],
        onChange: (e) => form.setData(key, e.target.value),
        className: inputClass,
    });

    return (
        <OwnerLayout
            shop={shop}
            title="Settings"
            description="Your loyalty card, links and the counter QR code."
        >
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Panel className="lg:col-span-2" title="Shop settings">
                    <form onSubmit={submit} noValidate className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Shop name" error={form.errors.name}>
                                <input type="text" {...text("name")} />
                            </Field>
                            <Field
                                label="Stamps for a reward"
                                error={form.errors.max_stamps}
                                hint={`Between ${MIN_STAMPS} and ${MAX_STAMPS}.`}
                            >
                                <input
                                    type="number"
                                    min={MIN_STAMPS}
                                    max={MAX_STAMPS}
                                    value={form.data.max_stamps}
                                    onChange={(e) =>
                                        form.setData(
                                            "max_stamps",
                                            Number(e.target.value),
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field
                                    label="Reward"
                                    error={form.errors.reward_title}
                                    hint="Shown on every customer's card."
                                >
                                    <input
                                        type="text"
                                        {...text("reward_title")}
                                    />
                                </Field>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">
                                Links
                            </h3>
                            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Instagram URL"
                                    error={form.errors.instagram_url}
                                >
                                    <input
                                        type="url"
                                        placeholder="https://instagram.com/…"
                                        {...text("instagram_url")}
                                    />
                                </Field>
                                <Field
                                    label="Google review URL"
                                    error={form.errors.google_review_url}
                                    hint="Add your Google review link, then choose how customers should use it."
                                >
                                    <input
                                        id="google-review-url"
                                        type="url"
                                        placeholder="https://g.page/r/…"
                                        {...text("google_review_url")}
                                    />
                                </Field>
                                <div className="sm:col-span-2 rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                                    <Switch
                                        checked={form.data.google_review_direct}
                                        disabled={
                                            !form.data.google_review_url &&
                                            !form.data.google_review_direct
                                        }
                                        onChange={(enabled) =>
                                            form.setData(
                                                "google_review_direct",
                                                enabled,
                                            )
                                        }
                                        label="Go directly to Google Reviews"
                                        description={
                                            form.data.google_review_direct
                                                ? "The customer card action opens this Google link directly. In-app feedback is skipped."
                                                : "Customers send feedback to your shop in-app, then can optionally share it on Google."
                                        }
                                    />
                                    {!form.data.google_review_url && (
                                        <p className="mt-2 text-xs text-amber-800">
                                            Add a Google review URL above to
                                            enable direct Google reviews.
                                            Without a URL, the card uses in-app
                                            feedback.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">
                                Guest Wi-Fi
                            </h3>
                            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Network name"
                                    error={form.errors.wifi_ssid}
                                >
                                    <input type="text" {...text("wifi_ssid")} />
                                </Field>
                                <Field
                                    label="Password"
                                    error={form.errors.wifi_password}
                                >
                                    <input
                                        type="text"
                                        {...text("wifi_password")}
                                    />
                                </Field>
                            </div>
                        </div>

                        <div className="flex items-center gap-3 border-t border-brand-border pt-5">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className={primaryButton}
                            >
                                {form.processing ? "Saving…" : "Save changes"}
                            </button>
                            {form.recentlySuccessful && (
                                <span className="text-sm text-green-700">
                                    Saved
                                </span>
                            )}
                        </div>
                    </form>
                </Panel>

                {!cardUrl && !shop.can_order ? (
                    // Outside the UK: no counter display - the QR codes we set up for them.
                    <Panel
                        title="Your QR code"
                        description="How customers join your loyalty card."
                    >
                        <div className="text-center">
                            <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                                <LuQrCode className="h-7 w-7" />
                            </span>
                            <p className="mt-3 text-sm text-brand-text">
                                Customers scan your QR code to get their card.
                                Download it, print it and put it by the till.
                            </p>
                            <Link
                                href="/dashboard/qr-codes"
                                className={`${primaryButton} mt-4`}
                            >
                                <LuDownload className="h-4 w-4" /> Your QR codes
                            </Link>
                        </div>
                    </Panel>
                ) : !cardUrl ? (
                    <Panel
                        title="Counter display"
                        description="How customers join your loyalty card."
                    >
                        <div className="text-center">
                            <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                                <LuNfc className="h-7 w-7" />
                            </span>
                            <p className="mt-3 text-sm text-brand-text">
                                Customers tap their phone on your TaDa Tap
                                counter display, or scan its QR code, to get
                                their card.
                            </p>
                            <Link
                                href="/dashboard/orders"
                                className={`${primaryButton} mt-4`}
                            >
                                Order a counter display
                            </Link>
                        </div>
                    </Panel>
                ) : (
                <Panel
                    title="Counter QR code"
                    description="Print it and put it by the till. Customers scan it to get their card."
                >
                    <div className="text-center">
                        {posterQr && (
                            <img
                                src={posterQr}
                                alt="Customer card QR code"
                                className="mx-auto h-52 w-52 rounded-xl border border-brand-border bg-white p-2"
                            />
                        )}
                        <p className="mt-3 break-all text-xs text-brand-muted">
                            {cardUrl}
                        </p>
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            {posterQr && (
                                <a
                                    href={posterQr}
                                    download={`${shop.slug}-qr.png`}
                                    className={primaryButton}
                                >
                                    <LuDownload className="h-4 w-4" /> Download
                                </a>
                            )}
                            <a
                                href={`/s/${shop.slug}?preview=1`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={secondaryButton}
                            >
                                <LuExternalLink className="h-4 w-4" /> Open
                            </a>
                        </div>
                    </div>
                </Panel>
                )}

                {/* Below the shop settings (the QR sits beside them in the first row). */}
                <ContactPanel contact={contact} countries={countries} />
            </div>
        </OwnerLayout>
    );
}
