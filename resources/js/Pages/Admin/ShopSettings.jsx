import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import { LuCircleCheck, LuEye, LuPackage, LuPlus } from "react-icons/lu";
import AddressLookup from "@/Components/AddressLookup";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { StampStepper } from "@/Components/Dashboard/ShopFields";
import {
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

/**
 * Wraps its input in a <label> so clicking the label focuses it. Pass `id` for
 * controls with buttons inside (the stamp stepper): a wrapping label would
 * "click" its first button (−) whenever any empty space in it is clicked.
 */
function Field({ id, label, error, hint, children }) {
    const Wrapper = id ? 'div' : 'label';
    const Label = id ? 'label' : 'span';

    return (
        <Wrapper className="block min-w-0">
            <Label htmlFor={id} className="mb-1.5 block text-sm font-medium text-brand-text">
                {label}
            </Label>
            {children}
            {hint && !error && (
                <span className="mt-1 block text-xs text-brand-muted">
                    {hint}
                </span>
            )}
            <FieldError message={error} />
        </Wrapper>
    );
}

/** One line of an order's status, for the shop page list. */
function orderStatusText(order) {
    if (order.status === "cancelled") return "Cancelled";
    if (order.awaiting_payment) return `Awaiting payment · ${order.reference}`;
    if (!order.stage) return "Checkout not finished";
    const step = order.steps.find((s) => s.key === order.stage);

    return `${step.label}, ${step.date}`;
}

/**
 * The shop's product orders, and creating one arranged with the owner (phone,
 * visit, promotion): paid already, or awaiting their bank transfer until
 * confirmed. Any paid order hides the owner's "Order your counter display"
 * banner, and so does one awaiting their transfer.
 */
function OrdersPanel({
    shop,
    orders,
    products,
    productOrderedAt,
    deliveryAddress,
    bankDetailsSet,
}) {
    const [open, setOpen] = useState(false);

    return (
        <Panel
            className="mb-4"
            title="Counter display & orders"
            description="Owners can pay online from their dashboard. Arranged it with them yourself? Create the order here."
            action={
                !open &&
                products.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className={secondaryButton}
                    >
                        <LuPlus className="h-4 w-4" /> New order
                    </button>
                )
            }
        >
            {productOrderedAt ? (
                <p className="flex items-start gap-2 rounded-xl bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800">
                    <LuCircleCheck className="mt-0.5 h-4 w-4 shrink-0" />
                    Ordered on {productOrderedAt}. The owner no longer sees the
                    order banner.
                </p>
            ) : (
                <p className="rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-800">
                    Not ordered yet - the owner sees “Order your counter
                    display” on their dashboard until an order is paid or
                    arranged here.
                </p>
            )}

            {open && (
                <div className="mt-4 rounded-xl border border-brand-border p-4">
                    <ManualOrderForm
                        shop={shop}
                        products={products}
                        deliveryAddress={deliveryAddress}
                        bankDetailsSet={bankDetailsSet}
                        onDone={() => setOpen(false)}
                    />
                </div>
            )}

            {orders.length > 0 && (
                <ul className="mt-4 divide-y divide-brand-border rounded-xl border border-brand-border">
                    {orders.map((order) => (
                        <li
                            key={order.id}
                            className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-sm"
                        >
                            <LuPackage className="h-4 w-4 shrink-0 text-brand-muted" />
                            <span className="min-w-0 flex-1 truncate text-brand-text">
                                #{order.id} · {order.quantity} ×{" "}
                                {order.product_name}
                            </span>
                            <span className="tabular-nums text-brand-text">
                                {order.total_pence === 0 && order.list_total_pence > 0
                                    ? "Free"
                                    : formatPence(order.total_pence)}
                                {order.list_total_pence > order.total_pence &&
                                    order.total_pence > 0 && (
                                        <span className="text-emerald-700">
                                            {" "}
                                            (
                                            {formatPence(
                                                order.list_total_pence -
                                                    order.total_pence,
                                            )}{" "}
                                            off)
                                        </span>
                                    )}
                                <span className="text-brand-muted">
                                    {" "}
                                    · {order.payment_label}
                                </span>
                            </span>
                            <span className="text-brand-muted">
                                {orderStatusText(order)}
                            </span>
                            <OrderPaymentActions order={order} compact />
                            <OrderEditDialog
                                order={{ ...order, shop_name: null }}
                                products={products}
                                buttonClassName={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}
                            />
                            <OrderStageEditor
                                order={order}
                                buttonClassName={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
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
}) {
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
        contact_phone: shop.contact_phone ?? "",
        address_line1: shop.address_line1 ?? "",
        address_line2: shop.address_line2 ?? "",
        town: shop.town ?? "",
        postcode: shop.postcode ?? "",
        // Ticked = post orders to the shop address (no separate one saved).
        delivery_same: !shop.delivery_address,
        delivery_address: shop.delivery_address ?? "",
    });

    // Unticking starts the box from the shop address, so only the difference needs typing.
    function setDeliverySame(same) {
        form.setData((data) => ({
            ...data,
            delivery_same: same,
            delivery_address:
                !same && !data.delivery_address.trim()
                    ? [
                          data.name,
                          data.address_line1,
                          data.address_line2,
                          [data.town, data.postcode].filter(Boolean).join(", "),
                      ]
                          .map((line) => (line ?? "").trim())
                          .filter(Boolean)
                          .join("\n")
                    : data.delivery_address,
        }));
    }

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
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link href="/admin" className={secondaryButton}>
                        Back to shops
                    </Link>
                    {ownerEmail && (
                        <Link
                            href={`/admin/shops/${shop.id}/view-as-owner`}
                            method="post"
                            as="button"
                            className={primaryButton}
                        >
                            <LuEye className="h-4 w-4" /> View as owner
                        </Link>
                    )}
                </div>
            }
        >
            <OrdersPanel
                shop={shop}
                orders={orders}
                products={products}
                productOrderedAt={productOrderedAt}
                deliveryAddress={deliveryAddress}
                bankDetailsSet={bankDetailsSet}
            />

            <form onSubmit={submit} noValidate className="space-y-4">
                <Panel
                    title="Loyalty card"
                    description="The details customers see on their card."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Shop name" error={form.errors.name}>
                            <input type="text" {...text("name")} />
                        </Field>
                        <Field
                            label="Shop link"
                            hint="The shop URL slug cannot be changed here."
                        >
                            <input
                                value={`/s/${shop.slug}`}
                                readOnly
                                className={`${inputClass} bg-brand-bg text-brand-muted`}
                            />
                        </Field>
                        <Field
                            id="admin-shop-max-stamps"
                            label="Stamps for a reward"
                            error={form.errors.max_stamps}
                        >
                            <StampStepper
                                id="admin-shop-max-stamps"
                                value={form.data.max_stamps}
                                onChange={(value) =>
                                    form.setData("max_stamps", value)
                                }
                            />
                        </Field>
                        <Field label="Reward" error={form.errors.reward_title}>
                            <input type="text" {...text("reward_title")} />
                        </Field>
                        <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5 sm:col-span-2">
                            <Switch
                                checked={form.data.show_card_link}
                                onChange={(on) =>
                                    form.setData("show_card_link", on)
                                }
                                label="Show the card link to the owner"
                                description={
                                    form.data.show_card_link
                                        ? "The owner sees their card link, a printable counter QR (Settings) and “Customer page” links."
                                        : "Hidden (default): customers join through our counter display, so the owner can't print their own QR."
                                }
                            />
                        </div>
                    </div>
                </Panel>

                <Panel
                    title="Customer links"
                    description="Choose what customers can open from their card."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
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
                            hint="Required to enable direct Google Reviews."
                        >
                            <input
                                type="url"
                                placeholder="https://g.page/r/…"
                                {...text("google_review_url")}
                            />
                        </Field>
                        <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5 sm:col-span-2">
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
                                        ? "The customer card action opens Google directly; in-app feedback is skipped."
                                        : "Customers leave feedback in-app first, then can optionally share it on Google."
                                }
                            />
                        </div>
                    </div>
                </Panel>

                <Panel
                    title="Guest Wi-Fi"
                    description="Optional network details shown to customers."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
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
                            <input type="text" {...text("wifi_password")} />
                        </Field>
                    </div>
                </Panel>

                <Panel
                    title="Business contact & location"
                    description="Optional contact and address details collected for this shop."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Contact person"
                            error={form.errors.contact_name}
                        >
                            <input type="text" {...text("contact_name")} />
                        </Field>
                        <div className="min-w-0">
                            <Field
                                label="Contact email"
                                error={form.errors.contact_email}
                            >
                                <input
                                    type="email"
                                    {...text("contact_email")}
                                />
                            </Field>
                            {/* Defaults to the owner's login; offer it back if changed. */}
                            {ownerEmail &&
                                !form.errors.contact_email &&
                                (form.data.contact_email.trim().toLowerCase() ===
                                ownerEmail.toLowerCase() ? (
                                    <p className="mt-1 text-xs text-brand-muted">
                                        Same as the owner's login.
                                    </p>
                                ) : (
                                    <p className="mt-1 text-xs text-brand-muted">
                                        Owner's login: {ownerEmail} ·{" "}
                                        <button
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    "contact_email",
                                                    ownerEmail,
                                                )
                                            }
                                            className="font-semibold text-brand-accent hover:underline"
                                        >
                                            Use this
                                        </button>
                                    </p>
                                ))}
                        </div>
                        <Field
                            label="Contact phone"
                            error={form.errors.contact_phone}
                            hint="Use international format, e.g. +442079460000."
                        >
                            <input type="tel" {...text("contact_phone")} />
                        </Field>
                        {/* findaddress.io via our /address-lookup proxy (key stays server-side). */}
                        <div className="sm:col-span-2">
                            <AddressLookup
                                initialPostcode={form.data.postcode}
                                onFound={(address) =>
                                    form.setData((data) => ({
                                        ...data,
                                        ...address,
                                    }))
                                }
                                inputClassName={inputClass}
                                buttonClassName={secondaryButton}
                            />
                        </div>
                        <Field
                            label="Address line 1"
                            error={form.errors.address_line1}
                        >
                            <input type="text" {...text("address_line1")} />
                        </Field>
                        <Field
                            label="Address line 2"
                            error={form.errors.address_line2}
                        >
                            <input type="text" {...text("address_line2")} />
                        </Field>
                        <Field label="Town / city" error={form.errors.town}>
                            <input type="text" {...text("town")} />
                        </Field>
                        <Field
                            label="Postcode / PIN code"
                            error={form.errors.postcode}
                        >
                            <input
                                type="text"
                                autoCapitalize="characters"
                                {...text("postcode")}
                            />
                        </Field>
                        <div className="space-y-3 sm:col-span-2">
                            <label className="flex cursor-pointer items-center gap-3 text-sm text-brand-text">
                                <input
                                    type="checkbox"
                                    checked={form.data.delivery_same}
                                    onChange={(e) =>
                                        setDeliverySame(e.target.checked)
                                    }
                                    className="h-4 w-4 accent-[var(--color-brand-accent)]"
                                />
                                Orders are delivered to the shop address
                            </label>
                            {!form.data.delivery_same && (
                                <Field
                                    label="Delivery address"
                                    error={form.errors.delivery_address}
                                >
                                    <textarea
                                        rows={3}
                                        value={form.data.delivery_address}
                                        onChange={(e) =>
                                            form.setData(
                                                "delivery_address",
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            "Name / company\nStreet\nTown, postcode"
                                        }
                                        className={`${inputClass} resize-y`}
                                    />
                                </Field>
                            )}
                        </div>
                    </div>
                </Panel>

                <div className="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className={primaryButton}
                    >
                        {form.processing ? "Saving…" : "Save shop settings"}
                    </button>
                    <Link href="/admin" className={secondaryButton}>
                        Cancel
                    </Link>
                    {form.recentlySuccessful && (
                        <span className="text-sm text-green-700">Saved</span>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
