import { Link, router, useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import {
    LuBuilding2,
    LuExternalLink,
    LuEye,
    LuPackage,
    LuPlus,
    LuSmartphone,
    LuStamp,
    LuToggleRight,
    LuUtensils,
} from "react-icons/lu";
import AddressLookup from "@/Components/AddressLookup";
import CountrySelect from "@/Components/CountrySelect";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import PhoneField from "@/Components/PhoneField";
import { dialCodeOf, INDIA, phonePlaceholder, UK } from "@/lib/validation";
import { StampStepper } from "@/Components/Dashboard/ShopFields";
import {
    CopyButton,
    FieldError,
    inputClass,
    Panel,
    primaryButton,
    secondaryButton,
    Switch,
} from "@/Components/Dashboard/Ui";
import ManualOrderForm from "@/Components/Dashboard/ManualOrderForm";
import OrderEditDialog from "@/Components/Dashboard/OrderEditDialog";
import OrderPaymentActions from "@/Components/Dashboard/OrderPaymentActions";
import OrderStageEditor from "@/Components/Dashboard/OrderStageEditor";
import { formatPence } from "@/lib/money";

/** The page's tabs, and which form fields live on each (to flag errors). */
const TABS = [
    { key: "card", label: "Loyalty card", icon: LuStamp, fields: ["name", "max_stamps", "reward_title", "show_card_link", "qr_design_id"] },
    {
        key: "customer",
        label: "Customer page",
        icon: LuSmartphone,
        fields: ["instagram_url", "google_review_url", "google_review_direct", "wifi_ssid", "wifi_password"],
    },
    { key: "menu", label: "Menu", icon: LuUtensils, fields: [] },
    { key: "features", label: "Features", icon: LuToggleRight, fields: [] },
    { key: "orders", label: "Orders", icon: LuPackage, fields: [] },
    {
        key: "business",
        label: "Business",
        icon: LuBuilding2,
        fields: [
            "contact_name", "contact_email", "contact_phone_code", "contact_phone",
            "address_line1", "address_line2", "town", "postcode", "country", "delivery_address",
        ],
    },
];

function initialTab() {
    const hash = typeof window !== "undefined" ? window.location.hash.slice(1) : "";
    return TABS.some((t) => t.key === hash) ? hash : "card";
}

/**
 * One setting: label (and an optional short hint) on the left, the control
 * on the right; stacked on phones. Rows sit in one panel, split by hairlines.
 */
function Row({ id, label, hint, error, children }) {
    return (
        <div className="grid gap-x-6 gap-y-1.5 px-5 py-4 sm:grid-cols-[13rem_minmax(0,1fr)]">
            <div>
                <label htmlFor={id} className="block text-sm font-medium text-brand-text">
                    {label}
                </label>
                {hint && <p className="mt-0.5 text-xs text-brand-muted">{hint}</p>}
            </div>
            <div className="min-w-0 max-w-xl">
                {children}
                <FieldError message={error} />
            </div>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <Panel title={title} bodyClassName="divide-y divide-brand-border">
            {children}
        </Panel>
    );
}

/** One line of an order's status. */
function orderStatusText(order) {
    if (order.status === "cancelled") return "Cancelled";
    if (order.awaiting_payment) return `Awaiting payment · ${order.reference}`;
    if (!order.stage) return "Checkout not finished";
    const step = order.steps.find((s) => s.key === order.stage);

    return `${step.label}, ${step.date}`;
}

/**
 * The shop's product orders, and recording one arranged with the owner
 * (phone, visit, promotion). Any paid or arranged order hides the owner's
 * "Order your counter display" banner.
 */
function OrdersTab({ shop, orders, products, productOrderedAt, deliveryAddress, bankDetailsSet }) {
    const [open, setOpen] = useState(false);

    return (
        <Panel
            title="Counter display & orders"
            description={
                productOrderedAt
                    ? `Ordered on ${productOrderedAt}.`
                    : "Not ordered yet - the owner sees an order banner until they do."
            }
            action={
                !open &&
                products.length > 0 && (
                    <button type="button" onClick={() => setOpen(true)} className={secondaryButton}>
                        <LuPlus className="h-4 w-4" /> New order
                    </button>
                )
            }
            bodyClassName=""
        >
            {open && (
                <div className="border-b border-brand-border p-5">
                    <ManualOrderForm
                        shop={shop}
                        products={products}
                        deliveryAddress={deliveryAddress}
                        bankDetailsSet={bankDetailsSet}
                        onDone={() => setOpen(false)}
                    />
                </div>
            )}

            {orders.length === 0 ? (
                !open && <p className="px-5 py-8 text-center text-sm text-brand-muted">No orders yet.</p>
            ) : (
                <ul className="divide-y divide-brand-border">
                    {orders.map((order) => (
                        <li key={order.id} className="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 text-sm">
                            <span className="min-w-0 flex-1 truncate text-brand-text">
                                #{order.id} · {order.quantity} × {order.product_name}
                            </span>
                            <span className="tabular-nums text-brand-text">
                                {order.total_pence === 0 && order.list_total_pence > 0 ? "Free" : formatPence(order.total_pence)}
                                {order.list_total_pence > order.total_pence && order.total_pence > 0 && (
                                    <span className="text-emerald-700">
                                        {" "}({formatPence(order.list_total_pence - order.total_pence)} off)
                                    </span>
                                )}
                                <span className="text-brand-muted"> · {order.payment_label}</span>
                            </span>
                            <span className="text-brand-muted">{orderStatusText(order)}</span>
                            <OrderPaymentActions order={order} compact />
                            <OrderEditDialog
                                order={{ ...order, shop_name: null }}
                                products={products}
                                buttonClassName={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}
                            />
                            <OrderStageEditor order={order} buttonClassName={`${secondaryButton} !px-2.5 !py-1.5 text-xs`} />
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

/**
 * Owner features for this shop: follow the platform default (Admin →
 * Settings → Features) or switch on / off just here. Saved on click.
 */
function FeaturesTab({ shop, features }) {
    const [saving, setSaving] = useState(null);

    function choose(key, choice) {
        router.put(`/admin/shops/${shop.id}/features`, { features: { [key]: choice } }, {
            preserveScroll: true,
            onStart: () => setSaving(key),
            onFinish: () => setSaving(null),
        });
    }

    return (
        <Section title="Features">
            {features.map((f) => {
                const current = f.override === null ? "default" : f.override ? "on" : "off";
                const effective = f.override ?? f.default;
                const options = [
                    ["default", `Default (${f.default ? "On" : "Off"})`],
                    ["on", "On"],
                    ["off", "Off"],
                ];

                return (
                    <Row key={f.key} label={f.label} hint={f.description}>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className={`flex rounded-lg bg-brand-bg p-0.5 ring-1 ring-brand-border ${saving === f.key ? "opacity-60" : ""}`} role="radiogroup" aria-label={f.label}>
                                {options.map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        role="radio"
                                        aria-checked={current === value}
                                        disabled={saving !== null}
                                        onClick={() => current !== value && choose(f.key, value)}
                                        className={`rounded-md px-3 py-1.5 text-xs font-semibold transition-colors ${
                                            current === value ? "bg-brand-card text-brand-text shadow-sm" : "text-brand-muted hover:text-brand-text"
                                        }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            <span className={`inline-flex items-center gap-1.5 text-xs font-medium ${effective ? "text-emerald-700" : "text-brand-muted"}`}>
                                <span className={`h-1.5 w-1.5 rounded-full ${effective ? "bg-emerald-500" : "bg-brand-muted/60"}`} />
                                {effective ? "On for this shop" : "Off for this shop"}
                            </span>
                        </div>
                    </Row>
                );
            })}
        </Section>
    );
}

function MenuTab({ shop, menuItemsCount, menuUrl }) {
    return (
        <Section title="Menu">
            <Row label="Menu" hint="Read from a photo with AI, then checked and saved.">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-sm text-brand-text">
                        {menuItemsCount > 0 ? `${menuItemsCount} items` : "Not set up"}
                    </span>
                    <Link href={`/admin/shops/${shop.id}/menu`} className={primaryButton}>
                        <LuUtensils className="h-4 w-4" /> {menuItemsCount > 0 ? "Edit menu" : "Create menu"}
                    </Link>
                </div>
            </Row>
            {menuItemsCount > 0 && (
                <Row label="Menu link" hint="Map a QR sticker to it (QR codes → Menu).">
                    <div className="flex items-center gap-1">
                        <input value={menuUrl} readOnly className={`${inputClass} bg-brand-bg text-brand-muted`} />
                        <CopyButton text={menuUrl} label="Copy menu link" />
                        <a href={menuUrl} target="_blank" rel="noreferrer" className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-bg hover:text-brand-text" aria-label="Open menu page">
                            <LuExternalLink className="h-4 w-4" />
                        </a>
                    </div>
                </Row>
            )}
        </Section>
    );
}

export default function ShopSettings({
    shop,
    orders,
    products,
    productOrderedAt,
    deliveryAddress,
    bankDetailsSet,
    ownerEmail,
    menuItemsCount,
    menuUrl,
    features,
    previewUrl,
    countries,
    assignedQrCount,
    qrDesigns,
    defaultQrDesign,
}) {
    const [tab, setTab] = useState(initialTab);
    const form = useForm({
        name: shop.name ?? "",
        max_stamps: shop.max_stamps,
        reward_title: shop.reward_title ?? "",
        google_review_url: shop.google_review_url ?? "",
        google_review_direct: Boolean(shop.google_review_direct),
        show_card_link: Boolean(shop.show_card_link),
        instagram_url: shop.instagram_url ?? "",
        wifi_ssid: shop.wifi_ssid ?? "",
        wifi_password: shop.wifi_password ?? "",
        contact_name: shop.contact_name ?? "",
        contact_email: shop.contact_email ?? "",
        contact_phone_code: shop.contact_phone_code ?? dialCodeOf(shop.country ?? UK, countries),
        contact_phone: shop.contact_phone ?? "",
        address_line1: shop.address_line1 ?? "",
        address_line2: shop.address_line2 ?? "",
        town: shop.town ?? "",
        postcode: shop.postcode ?? "",
        country: shop.country ?? UK,
        qr_design_id: shop.qr_design_id ?? "",
        // Ticked = post orders to the shop address (no separate one saved).
        delivery_same: !shop.delivery_address,
        delivery_address: shop.delivery_address ?? "",
    });
    const inUk = form.data.country === UK;

    const tabHasError = (t) => t.fields.some((field) => form.errors[field]);

    function openTab(key) {
        setTab(key);
        window.history.replaceState(window.history.state, "", `#${key}`);
    }

    // After a failed save, show the first tab with a problem on it.
    useEffect(() => {
        if (TABS.find((t) => t.key === tab && tabHasError(t))) return;
        const withError = TABS.find(tabHasError);
        if (withError) openTab(withError.key);
    }, [form.errors]);

    // Unticking starts the box from the shop address, so only the difference needs typing.
    function setDeliverySame(same) {
        form.setData((data) => ({
            ...data,
            delivery_same: same,
            delivery_address:
                !same && !data.delivery_address.trim()
                    ? [data.name, data.address_line1, data.address_line2, [data.town, data.postcode].filter(Boolean).join(", ")]
                          .map((line) => (line ?? "").trim())
                          .filter(Boolean)
                          .join("\n")
                    : data.delivery_address,
        }));
    }

    const text = (key) => ({
        id: key,
        value: form.data[key],
        onChange: (event) => form.setData(key, event.target.value),
        className: inputClass,
    });

    function submit(event) {
        event.preventDefault();
        form.put(`/admin/shops/${shop.id}/settings`, {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    }

    const emailIsLogin = ownerEmail && form.data.contact_email.trim().toLowerCase() === ownerEmail.toLowerCase();

    return (
        <AdminLayout
            title={shop.name}
            description={ownerEmail ? `Owner: ${ownerEmail}` : "No owner account"}
            actions={
                <div className="flex flex-wrap gap-2">
                    <a href={previewUrl} target="_blank" rel="noreferrer" className={secondaryButton}>
                        <LuExternalLink className="h-4 w-4" /> Customer page
                    </a>
                    {ownerEmail && (
                        <Link href={`/admin/shops/${shop.id}/view-as-owner`} method="post" as="button" className={primaryButton}>
                            <LuEye className="h-4 w-4" /> View as owner
                        </Link>
                    )}
                </div>
            }
        >
            <nav role="tablist" aria-label="Shop settings" className="-mx-1 mb-4 flex gap-1 overflow-x-auto border-b border-brand-border px-1 [scrollbar-width:none]">
                {TABS.map((t) => {
                    const count = t.key === "menu" ? menuItemsCount : t.key === "orders" ? orders.length : null;

                    return (
                        <button
                            key={t.key}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.key}
                            onClick={() => openTab(t.key)}
                            className={`-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                                tab === t.key ? "border-brand-accent text-brand-text" : "border-transparent text-brand-muted hover:text-brand-text"
                            }`}
                        >
                            <t.icon className="h-4 w-4" />
                            {t.label}
                            {count > 0 && <span className="text-xs tabular-nums text-brand-muted">{count}</span>}
                            {tabHasError(t) && <span className="h-1.5 w-1.5 rounded-full bg-red-600" aria-label="has errors" />}
                        </button>
                    );
                })}
            </nav>

            {tab === "menu" && <MenuTab shop={shop} menuItemsCount={menuItemsCount} menuUrl={menuUrl} />}
            {tab === "features" && <FeaturesTab shop={shop} features={features} />}

            {tab === "orders" && (
                <OrdersTab
                    shop={shop}
                    orders={orders}
                    products={products}
                    productOrderedAt={productOrderedAt}
                    deliveryAddress={deliveryAddress}
                    bankDetailsSet={bankDetailsSet}
                />
            )}

            {/* One form across the card / customer page / business tabs, one save. */}
            <form onSubmit={submit} noValidate className="space-y-4">
                {tab === "card" && (
                    <Section title="Loyalty card">
                        <Row id="name" label="Shop name" error={form.errors.name}>
                            <input type="text" {...text("name")} />
                        </Row>
                        <Row id="admin-shop-max-stamps" label="Stamps for a reward" error={form.errors.max_stamps}>
                            <StampStepper
                                id="admin-shop-max-stamps"
                                value={form.data.max_stamps}
                                onChange={(value) => form.setData("max_stamps", value)}
                            />
                        </Row>
                        <Row id="reward_title" label="Reward" error={form.errors.reward_title}>
                            <input type="text" placeholder="Free coffee" {...text("reward_title")} />
                        </Row>
                        <Row label="Card link" hint="Can't be changed.">
                            <div className="flex items-center gap-1">
                                <input value={`/s/${shop.slug}`} readOnly className={`${inputClass} bg-brand-bg text-brand-muted`} />
                                <CopyButton text={`${window.location.origin}/s/${shop.slug}`} label="Copy card link" />
                            </div>
                        </Row>
                        <Row label="Owner sees the card link" hint="Off: they order our counter display instead of printing their own QR.">
                            <Switch checked={form.data.show_card_link} onChange={(on) => form.setData("show_card_link", on)} />
                        </Row>
                        <Row
                            id="qr_design_id"
                            label="Owner's QR download"
                            hint={
                                inUk
                                    ? "For shops outside the UK (they can't order a counter display)."
                                    : "Outside the UK: the owner downloads this shop's QR codes in this design."
                            }
                            error={form.errors.qr_design_id}
                        >
                            <select
                                id="qr_design_id"
                                value={form.data.qr_design_id}
                                onChange={(e) => form.setData("qr_design_id", e.target.value)}
                                className={inputClass}
                            >
                                <option value="">{defaultQrDesign ? `Default design (${defaultQrDesign})` : "Plain QR (no default design set)"}</option>
                                {qrDesigns.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name || `Design #${d.id}`}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-brand-muted">
                                {assignedQrCount > 0
                                    ? `${assignedQrCount} QR ${assignedQrCount === 1 ? "code" : "codes"} assigned to this shop.`
                                    : inUk
                                      ? "No QR codes assigned yet - map one to this shop on the QR codes page."
                                      : "None yet - one is issued automatically when the owner opens their QR codes page."}{" "}
                                <Link href="/admin/qr-codes" className="font-semibold text-brand-accent hover:underline">
                                    QR codes
                                </Link>
                            </p>
                        </Row>
                    </Section>
                )}

                {tab === "customer" && (
                    <>
                        <Section title="Reviews & social">
                            <Row id="instagram_url" label="Instagram" error={form.errors.instagram_url}>
                                <input type="url" placeholder="https://instagram.com/…" {...text("instagram_url")} />
                            </Row>
                            <Row id="google_review_url" label="Google review link" error={form.errors.google_review_url}>
                                <input type="url" placeholder="https://g.page/r/…" {...text("google_review_url")} />
                            </Row>
                            <Row
                                label="Send reviews straight to Google"
                                hint={form.data.google_review_url ? "Off: customers rate in-app first." : "Add a Google review link first."}
                            >
                                <Switch
                                    checked={form.data.google_review_direct}
                                    disabled={!form.data.google_review_url && !form.data.google_review_direct}
                                    onChange={(on) => form.setData("google_review_direct", on)}
                                />
                            </Row>
                        </Section>
                        <Section title="Guest Wi-Fi">
                            <Row id="wifi_ssid" label="Network name" hint="Shown to customers with a card." error={form.errors.wifi_ssid}>
                                <input type="text" {...text("wifi_ssid")} />
                            </Row>
                            <Row id="wifi_password" label="Password" error={form.errors.wifi_password}>
                                <input type="text" {...text("wifi_password")} />
                            </Row>
                        </Section>
                    </>
                )}

                {tab === "business" && (
                    <>
                        <Section title="Contact">
                            <Row id="contact_name" label="Contact person" error={form.errors.contact_name}>
                                <input type="text" {...text("contact_name")} />
                            </Row>
                            <Row
                                id="contact_email"
                                label="Email"
                                hint={emailIsLogin ? "Same as the owner's login." : null}
                                error={form.errors.contact_email}
                            >
                                <input type="email" {...text("contact_email")} />
                                {ownerEmail && !emailIsLogin && !form.errors.contact_email && (
                                    <p className="mt-1 text-xs text-brand-muted">
                                        Owner's login: {ownerEmail} ·{" "}
                                        <button
                                            type="button"
                                            onClick={() => form.setData("contact_email", ownerEmail)}
                                            className="font-semibold text-brand-accent hover:underline"
                                        >
                                            Use this
                                        </button>
                                    </p>
                                )}
                            </Row>
                            <Row
                                id="contact_phone"
                                label="Phone"
                                error={form.errors.contact_phone ?? form.errors.contact_phone_code}
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
                            </Row>
                        </Section>

                        <Section title="Address">
                            <Row
                                id="country"
                                label="Country"
                                hint="Only UK shops can order the counter display; elsewhere the owner downloads their QR codes."
                                error={form.errors.country}
                            >
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
                            </Row>
                            {/* findaddress.io via our /address-lookup proxy (key stays server-side) - UK addresses only. */}
                            {inUk && (
                                <Row label="Find address">
                                    <AddressLookup
                                        initialPostcode={form.data.postcode}
                                        onFound={(address) => form.setData((data) => ({ ...data, ...address }))}
                                        inputClassName={inputClass}
                                        buttonClassName={secondaryButton}
                                    />
                                </Row>
                            )}
                            <Row id="address_line1" label="Address line 1" error={form.errors.address_line1}>
                                <input type="text" {...text("address_line1")} />
                            </Row>
                            <Row id="address_line2" label="Address line 2" error={form.errors.address_line2}>
                                <input type="text" {...text("address_line2")} />
                            </Row>
                            <Row id="town" label="Town / city" error={form.errors.town}>
                                <input type="text" {...text("town")} />
                            </Row>
                            <Row id="postcode" label={form.data.country === INDIA ? "PIN code" : "Postcode"} error={form.errors.postcode}>
                                <input type="text" autoCapitalize="characters" {...text("postcode")} />
                            </Row>
                            {inUk && (
                            <Row label="Deliver orders to" error={form.errors.delivery_address}>
                                <label className="flex cursor-pointer items-center gap-3 text-sm text-brand-text">
                                    <input
                                        type="checkbox"
                                        checked={form.data.delivery_same}
                                        onChange={(e) => setDeliverySame(e.target.checked)}
                                        className="h-4 w-4 accent-[var(--color-brand-accent)]"
                                    />
                                    The shop address
                                </label>
                                {!form.data.delivery_same && (
                                    <textarea
                                        rows={3}
                                        aria-label="Delivery address"
                                        value={form.data.delivery_address}
                                        onChange={(e) => form.setData("delivery_address", e.target.value)}
                                        placeholder={"Name / company\nStreet\nTown, postcode"}
                                        className={`${inputClass} mt-2 resize-y`}
                                    />
                                )}
                            </Row>
                            )}
                        </Section>
                    </>
                )}

                {/* Only while something's changed: one save for all three tabs. */}
                {(form.isDirty || form.processing) && (
                    <div className="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 border-t border-brand-border bg-brand-bg py-3">
                        <button type="submit" disabled={form.processing} className={primaryButton}>
                            {form.processing ? "Saving…" : "Save changes"}
                        </button>
                        <button type="button" disabled={form.processing} onClick={() => form.reset()} className={secondaryButton}>
                            Discard
                        </button>
                        <span className="text-sm text-brand-muted">Unsaved changes</span>
                    </div>
                )}
            </form>
        </AdminLayout>
    );
}
