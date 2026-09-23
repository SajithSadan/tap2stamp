import { router, useForm, usePage } from '@inertiajs/react';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { LuKeyRound, LuPlus, LuSmartphone, LuTrash2, LuUserCog } from 'react-icons/lu';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { Avatar, EmptyState, FieldError, inputClass, Panel, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';

// Digits only, max 6 - the server re-validates (4-6 digits).
const pinInputProps = {
    type: 'password',
    inputMode: 'numeric',
    autoComplete: 'new-password',
    maxLength: 6,
};

function onlyDigits(value) {
    return value.replace(/\D/g, '').slice(0, 6);
}

function ResetPinForm({ member, onDone }) {
    const form = useForm({ pin: '' });

    function submit(e) {
        e.preventDefault();
        form.put(`/dashboard/staff-members/${member.id}/pin`, { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="mt-3 flex items-start gap-2 sm:pl-12">
            <div className="min-w-0 flex-1 sm:max-w-40">
                <input
                    {...pinInputProps}
                    value={form.data.pin}
                    onChange={(e) => form.setData('pin', onlyDigits(e.target.value))}
                    placeholder="New PIN"
                    aria-label={`New PIN for ${member.name}`}
                    className={inputClass}
                    autoFocus
                />
                <FieldError message={form.errors.pin} />
            </div>
            <button type="submit" disabled={form.processing} className={`${primaryButton} h-[42px] shrink-0`}>
                Save PIN
            </button>
            <button type="button" onClick={onDone} className={`${secondaryButton} h-[42px] shrink-0`}>
                Cancel
            </button>
        </form>
    );
}

function StaffMembers({ members }) {
    const form = useForm({ name: '', pin: '' });
    const [resetting, setResetting] = useState(null);

    function submit(e) {
        e.preventDefault();
        form.post('/dashboard/staff-members', { preserveScroll: true, onSuccess: () => form.reset() });
    }

    function remove(member) {
        if (!window.confirm(`Remove ${member.name}? They won't be able to sign in any more. Their past stamps stay in your activity.`)) {
            return;
        }

        router.delete(`/dashboard/staff-members/${member.id}`, { preserveScroll: true });
    }

    return (
        <Panel title="Staff members" description="Each person signs in on a shop device with their name and PIN, so every stamp shows who gave it.">
            <form onSubmit={submit} noValidate className="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_9rem_auto]">
                <div>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
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
                        onChange={(e) => form.setData('pin', onlyDigits(e.target.value))}
                        placeholder="PIN (4–6 digits)"
                        aria-label="PIN"
                        className={inputClass}
                    />
                    <FieldError message={form.errors.pin} />
                </div>
                <button type="submit" disabled={form.processing} className={`${primaryButton} h-[42px]`}>
                    <LuPlus className="h-4 w-4" /> Add
                </button>
            </form>
            <p className="mt-2 text-xs text-brand-muted">Tell them their PIN in person. You can reset it here any time.</p>

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
                                            {member.stamps_today} today · {member.stamps_total} total
                                            {member.last_scan && ` · last scan ${member.last_scan}`}
                                        </p>
                                    </div>
                                </div>
                                <div className="-ml-2.5 flex shrink-0 gap-1 pl-12 sm:ml-0 sm:pl-0">
                                    <button
                                        type="button"
                                        onClick={() => setResetting(resetting === member.id ? null : member.id)}
                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-text hover:bg-brand-bg"
                                    >
                                        <LuKeyRound className="h-3.5 w-3.5" /> Reset PIN
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => remove(member)}
                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                    >
                                        <LuTrash2 className="h-3.5 w-3.5" /> Remove
                                    </button>
                                </div>
                            </div>
                            {resetting === member.id && <ResetPinForm member={member} onDone={() => setResetting(null)} />}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function StaffDevices({ devices }) {
    const { flash } = usePage().props;
    const [setupQr, setSetupQr] = useState(null);
    const form = useForm({ name: '' });

    useEffect(() => {
        if (!flash?.staffToken) {
            setSetupQr(null);
            return;
        }

        QRCode.toDataURL(`${window.location.origin}/staff/setup/${flash.staffToken}`, { margin: 1, width: 220 })
            .then(setSetupQr)
            .catch(() => setSetupQr(null));
    }, [flash?.staffToken]);

    function submit(e) {
        e.preventDefault();
        form.post('/dashboard/staff-devices', { preserveScroll: true, onSuccess: () => form.reset() });
    }

    function revoke(device) {
        if (!window.confirm(`Revoke ${device.name}? It will stop working straight away.`)) return;

        router.delete(`/dashboard/staff-devices/${device.id}`, { preserveScroll: true });
    }

    return (
        <Panel title="Shop devices" description="Approve each shop phone or tablet once. Staff then sign in on it with their PIN.">
            {flash?.staffToken && (
                <div className="mb-4 rounded-xl border border-brand-accent/40 bg-brand-accent/5 p-4 text-center">
                    <p className="text-sm font-semibold text-brand-text">Scan this with the new device now. It won't be shown again.</p>
                    {setupQr && (
                        <img src={setupQr} alt="Device setup QR code" className="mx-auto mt-3 h-44 w-44 rounded-lg border border-brand-border bg-white" />
                    )}
                    <p className="mt-2 break-all font-mono text-[11px] text-brand-muted">/staff/setup/{flash.staffToken}</p>
                </div>
            )}

            <form onSubmit={submit} noValidate className="flex flex-col gap-2 sm:flex-row">
                <input
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Device name, e.g. Counter iPad"
                    aria-label="Device name"
                    className={inputClass}
                />
                <button type="submit" disabled={form.processing} className={`${primaryButton} shrink-0 sm:h-[42px]`}>
                    <LuPlus className="h-4 w-4" /> Add device
                </button>
            </form>
            <FieldError message={form.errors.name} />

            {devices.length === 0 ? (
                <EmptyState icon={LuSmartphone} title="No devices yet">
                    Add the phone or tablet your staff scan cards with.
                </EmptyState>
            ) : (
                <ul className="mt-4 divide-y divide-brand-border rounded-xl border border-brand-border">
                    {devices.map((device) => (
                        <li key={device.id} className={`flex items-center gap-3 px-4 py-3 ${device.revoked ? 'opacity-60' : ''}`}>
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-bg text-brand-muted">
                                <LuSmartphone className="h-4 w-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-brand-text">{device.name}</p>
                                <p className="truncate text-xs text-brand-muted">
                                    {device.revoked
                                        ? 'Revoked'
                                        : [
                                              device.signed_in ? `${device.signed_in} signed in` : 'Nobody signed in',
                                              device.last_used_at ? `used ${device.last_used_at}` : `added ${device.created_at}`,
                                          ].join(' · ')}
                                </p>
                            </div>
                            {!device.revoked && (
                                <button
                                    type="button"
                                    onClick={() => revoke(device)}
                                    className="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                >
                                    Revoke
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

export default function Staff({ shop, staffMembers, staffDevices }) {
    return (
        <OwnerLayout shop={shop} title="Staff" description="Your team and the devices they scan cards on.">
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <StaffMembers members={staffMembers} />
                <StaffDevices devices={staffDevices} />
            </div>
        </OwnerLayout>
    );
}
