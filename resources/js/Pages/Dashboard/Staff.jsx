import { router, useForm, usePage } from "@inertiajs/react";
import QRCode from "qrcode";
import { useEffect, useState } from "react";
import {
    LuKeyRound,
    LuLink,
    LuMapPin,
    LuPlus,
    LuRefreshCw,
    LuTrash2,
    LuUserCog,
} from "react-icons/lu";
import { FaWhatsapp } from "react-icons/fa";
import { useConfirm } from "@/Components/ConfirmDialog";
import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import {
    Avatar,
    CopyButton,
    EmptyState,
    FieldError,
    inputClass,
    Panel,
    primaryButton,
    secondaryButton,
} from "@/Components/Dashboard/Ui";

// Digits only, max 6 - the server re-validates (4-6 digits).
const pinInputProps = {
    type: "password",
    inputMode: "numeric",
    autoComplete: "new-password",
    maxLength: 6,
};

function onlyDigits(value) {
    return value.replace(/\D/g, "").slice(0, 6);
}

function ResetPinForm({ member, onDone }) {
    const form = useForm({ pin: "" });

    function submit(e) {
        e.preventDefault();
        form.put(`/dashboard/staff-members/${member.id}/pin`, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="mt-3 flex items-start gap-2 sm:pl-12"
        >
            <div className="min-w-0 flex-1 sm:max-w-40">
                <input
                    {...pinInputProps}
                    value={form.data.pin}
                    onChange={(e) =>
                        form.setData("pin", onlyDigits(e.target.value))
                    }
                    placeholder="New PIN"
                    aria-label={`New PIN for ${member.name}`}
                    className={inputClass}
                    autoFocus
                />
                <FieldError message={form.errors.pin} />
            </div>
            <button
                type="submit"
                disabled={form.processing}
                className={`${primaryButton} h-[42px] shrink-0`}
            >
                Save PIN
            </button>
            <button
                type="button"
                onClick={onDone}
                className={`${secondaryButton} h-[42px] shrink-0`}
            >
                Cancel
            </button>
        </form>
    );
}

function StaffMembers({ members, confirm }) {
    const form = useForm({ name: "", pin: "" });
    const [resetting, setResetting] = useState(null);

    function submit(e) {
        e.preventDefault();
        form.post("/dashboard/staff-members", {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    async function remove(member) {
        const ok = await confirm({
            title: `Remove ${member.name}?`,
            message:
                "They won’t be able to sign in any more. Their past stamps stay in your activity.",
            confirmLabel: "Remove",
            danger: true,
        });
        if (!ok) return;

        router.delete(`/dashboard/staff-members/${member.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <Panel
            title="Staff members"
            description="Each person signs in at a shop location with their name and PIN, so every stamp shows who gave it."
        >
            <form
                onSubmit={submit}
                noValidate
                className="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_9rem_auto]"
            >
                <div>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        placeholder="Name, e.g. Sam"
                        aria-label="Staff member name"
                        className={inputClass}
                    />
                    <FieldError message={form.errors.name} />
                </div>
                <div>
                    <input
                        {...pinInputProps}
                        value={form.data.pin}
                        onChange={(e) =>
                            form.setData("pin", onlyDigits(e.target.value))
                        }
                        placeholder="PIN (4–6 digits)"
                        aria-label="PIN"
                        className={inputClass}
                    />
                    <FieldError message={form.errors.pin} />
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    className={`${primaryButton} h-[42px]`}
                >
                    <LuPlus className="h-4 w-4" /> Add
                </button>
            </form>
            <p className="mt-2 text-xs text-brand-muted">
                Tell them their PIN in person. You can reset it here any time.
            </p>

            {members.length === 0 ? (
                <EmptyState icon={LuUserCog} title="No staff yet">
                    Add your team so they can sign in on the scanner.
                </EmptyState>
            ) : (
                <ul className="mt-4 divide-y divide-brand-border rounded-xl border border-brand-border">
                    {members.map((member) => (
                        <li key={member.id} className="px-4 py-3">
                            {/* Phones: details on top, actions underneath (lined up with the text). */}
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <Avatar name={member.name} />
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-brand-text">
                                            {member.name}
                                            {member.signed_in_on && (
                                                <span className="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-800">
                                                    On {member.signed_in_on}
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-xs text-brand-muted">
                                            {member.stamps_today} today ·{" "}
                                            {member.stamps_total} total
                                            {member.last_scan &&
                                                ` · last scan ${member.last_scan}`}
                                        </p>
                                    </div>
                                </div>
                                <div className="-ml-2.5 flex shrink-0 gap-1 pl-12 sm:ml-0 sm:pl-0">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setResetting(
                                                resetting === member.id
                                                    ? null
                                                    : member.id,
                                            )
                                        }
                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-text hover:bg-brand-bg"
                                    >
                                        <LuKeyRound className="h-3.5 w-3.5" />{" "}
                                        Reset PIN
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => remove(member)}
                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                    >
                                        <LuTrash2 className="h-3.5 w-3.5" />{" "}
                                        Remove
                                    </button>
                                </div>
                            </div>
                            {resetting === member.id && (
                                <ResetPinForm
                                    member={member}
                                    onDone={() => setResetting(null)}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function StaffDevices({ devices, confirm }) {
    const { flash } = usePage().props;
    const [setupQr, setSetupQr] = useState(null);
    const form = useForm({ name: "" });
    const setupUrl = flash?.staffToken
        ? `${window.location.origin}/staff/setup/${flash.staffToken}`
        : "";
    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(`Set up the staff scanner for ${flash?.staffDeviceName ?? "this location"} by opening this link on its phone or tablet: ${setupUrl}`)}`;

    useEffect(() => {
        if (!flash?.staffToken) {
            setSetupQr(null);
            return;
        }

        QRCode.toDataURL(
            `${window.location.origin}/staff/setup/${flash.staffToken}`,
            { margin: 1, width: 220 },
        )
            .then(setSetupQr)
            .catch(() => setSetupQr(null));
    }, [flash?.staffToken]);

    function submit(e) {
        e.preventDefault();
        form.post("/dashboard/staff-devices", {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    async function revoke(device) {
        const ok = await confirm({
            title: `Remove ${device.name}?`,
            message:
                "Its scanner stops working straight away and it leaves this list. You can add the location again later.",
            confirmLabel: "Remove",
            danger: true,
        });
        if (!ok) return;

        router.delete(`/dashboard/staff-devices/${device.id}`, {
            preserveScroll: true,
        });
    }

    async function generateSetupLink(device) {
        const ok = await confirm({
            title: `Create a new setup link for ${device.name}?`,
            message:
                "This signs out the scanner at this location. Open the new link on its phone or tablet before staff scan again.",
            confirmLabel: "Create new link",
        });
        if (!ok) return;

        router.post(
            `/dashboard/staff-devices/${device.id}/setup-link`,
            {},
            { preserveScroll: true },
        );
    }

    return (
        <Panel
            title="Shop locations"
            description="Add each place your staff scan cards, like a branch or a counter. Staff sign in there with their PIN."
        >
            {flash?.staffToken && (
                <div className="mb-4 rounded-xl border border-brand-accent/40 bg-brand-accent/5 p-4 text-center">
                    <p className="text-sm font-semibold text-brand-text">
                        {flash.staffDeviceName
                            ? `Setup link for ${flash.staffDeviceName}. It won't be shown again.`
                            : "Scan this with the phone or tablet at this location now. It won't be shown again."}
                    </p>
                    {setupQr && (
                        <img
                            src={setupQr}
                            alt="Location setup QR code"
                            className="mx-auto mt-3 h-44 w-44 rounded-lg border border-brand-border bg-white"
                        />
                    )}
                    <div className="mx-auto mt-3 flex max-w-xl items-center gap-1 rounded-lg border border-brand-border bg-brand-card p-1.5 text-left">
                        <p className="min-w-0 flex-1 break-all font-mono text-xs text-brand-muted">
                            {setupUrl}
                        </p>
                        <CopyButton
                            text={setupUrl}
                            label="Copy location setup link"
                        />
                    </div>
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mx-auto mt-3 inline-flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-semibold text-white transition hover:brightness-95"
                    >
                        <FaWhatsapp className="h-4 w-4" /> Share via WhatsApp
                    </a>
                    <p className="mt-2 text-xs text-brand-muted">
                        WhatsApp will open with the link ready to send.
                    </p>
                </div>
            )}

            <form
                onSubmit={submit}
                noValidate
                className="flex flex-col gap-2 sm:flex-row"
            >
                <input
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                    placeholder="Location name, e.g. High Street branch"
                    aria-label="Location name"
                    className={inputClass}
                />
                <button
                    type="submit"
                    disabled={form.processing}
                    className={`${primaryButton} shrink-0 sm:h-[42px]`}
                >
                    <LuPlus className="h-4 w-4" /> Add location
                </button>
            </form>
            <FieldError message={form.errors.name} />

            {devices.length === 0 ? (
                <EmptyState icon={LuMapPin} title="No locations yet">
                    Add each place your staff scan cards.
                </EmptyState>
            ) : (
                <ul className="mt-4 divide-y divide-brand-border rounded-xl border border-brand-border">
                    {devices.map((device) => (
                        <li
                            key={device.id}
                            className="flex flex-wrap items-center gap-3 px-4 py-3"
                        >
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-bg text-brand-muted">
                                <LuMapPin className="h-4 w-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-brand-text">
                                    {device.name}
                                </p>
                                <p className="truncate text-xs text-brand-muted">
                                    {[
                                        device.signed_in
                                            ? `${device.signed_in} signed in`
                                            : "Nobody signed in",
                                        device.last_used_at
                                            ? `used ${device.last_used_at}`
                                            : `added ${device.created_at}`,
                                    ].join(" · ")}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    onClick={() => generateSetupLink(device)}
                                    className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-text hover:bg-brand-bg"
                                >
                                    <LuLink className="h-3.5 w-3.5" /> New
                                    setup link
                                </button>
                                <button
                                    type="button"
                                    onClick={() => revoke(device)}
                                    className="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                >
                                    Remove
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

/**
 * The shop's own code: staff open /staff on a new phone and type it to add
 * that phone (no setup QR needed). 3 wrong codes a day lock that phone.
 */
function ShopCode({ code, confirm }) {
    const staffUrl = `${window.location.origin}/staff`;

    async function regenerate() {
        const ok = await confirm({
            title: "Get a new shop code?",
            message:
                "The current code stops working straight away. Phones already set up keep working.",
            confirmLabel: "New code",
        });
        if (!ok) return;

        router.post("/dashboard/shop-code", {}, { preserveScroll: true });
    }

    const step = "flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-accent/10 text-xs font-bold text-brand-accent";

    return (
        <Panel
            title="Add a phone with a code"
            description="No QR needed. Staff set up their own phone in three steps."
        >
            <ol className="space-y-4">
                <li className="flex items-start gap-3">
                    <span className={step}>1</span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm text-brand-text">On the phone, open</p>
                        <div className="mt-1 flex items-center gap-1">
                            <p className="min-w-0 break-all font-mono text-sm font-medium text-brand-text">
                                {staffUrl.replace(/^https?:\/\//, "")}
                            </p>
                            <CopyButton text={staffUrl} label="Copy staff link" />
                        </div>
                    </div>
                </li>
                <li className="flex items-start gap-3">
                    <span className={step}>2</span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm text-brand-text">Type your shop code</p>
                        <div className="mt-1 flex items-center gap-1">
                            <p className="font-mono text-2xl font-bold tracking-[0.25em] text-brand-text">
                                {code}
                            </p>
                            <CopyButton text={code} label="Copy shop code" />
                        </div>
                    </div>
                </li>
                <li className="flex items-start gap-3">
                    <span className={step}>3</span>
                    <p className="text-sm text-brand-text">
                        They pick their name and enter their PIN. The phone
                        then shows in Shop locations below.
                    </p>
                </li>
            </ol>
            <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-brand-border pt-3">
                <p className="min-w-0 flex-1 text-xs text-brand-muted">
                    Keep the code within your team. 3 wrong tries lock a phone.
                </p>
                <button
                    type="button"
                    onClick={regenerate}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-text hover:bg-brand-bg"
                >
                    <LuRefreshCw className="h-3.5 w-3.5" /> New code
                </button>
            </div>
        </Panel>
    );
}

export default function Staff({ shop, shopCode, staffMembers, staffDevices }) {
    const [confirm, confirmDialog] = useConfirm();

    return (
        <OwnerLayout
            shop={shop}
            title="Staff"
            description="Your team and the locations they scan cards at."
        >
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <StaffMembers members={staffMembers} confirm={confirm} />
                <div className="flex flex-col gap-4">
                    {shopCode && <ShopCode code={shopCode} confirm={confirm} />}
                    <StaffDevices devices={staffDevices} confirm={confirm} />
                </div>
            </div>
            {confirmDialog}
        </OwnerLayout>
    );
}
