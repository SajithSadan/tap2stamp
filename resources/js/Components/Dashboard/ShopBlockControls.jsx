import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LuBan, LuCircleCheck, LuX } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import { FieldError, inputClass, secondaryButton } from '@/Components/Dashboard/Ui';

const EFFECTS = [
    "Customers see \"this card isn't active\" on its card page and menu. Their cards at other shops keep working.",
    "Staff can't sign in or give stamps.",
    'The owner sees "your shop is paused" instead of the dashboard.',
    'Nothing is deleted - unblocking brings everything back as it was.',
];

/** Admin shop settings header: "Block shop" + the dialog asking for a reason. */
export function BlockShopButton({ shop }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit(event) {
        event.preventDefault();
        form.put(`/admin/shops/${shop.id}/block`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className={`${secondaryButton} text-red-700 hover:bg-red-500/10`}>
                <LuBan className="h-4 w-4" /> Block shop
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={() => setOpen(false)}>
                    <form
                        onSubmit={submit}
                        noValidate
                        role="dialog"
                        aria-modal="true"
                        aria-label={`Block ${shop.name}`}
                        onClick={(e) => e.stopPropagation()}
                        className="w-full rounded-t-2xl bg-brand-card p-5 shadow-xl sm:max-w-md sm:rounded-2xl"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <h2 className="font-heading text-lg font-semibold text-brand-text">Block {shop.name}?</h2>
                            <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                                <LuX className="h-5 w-5" />
                            </button>
                        </div>

                        <ul className="mt-3 list-disc space-y-1.5 pl-5 text-sm text-brand-muted">
                            {EFFECTS.map((line) => (
                                <li key={line}>{line}</li>
                            ))}
                        </ul>

                        <label htmlFor="block-reason" className="mt-4 block text-sm font-medium text-brand-text">
                            Reason <span className="font-normal text-brand-muted">(optional, only admins see it)</span>
                        </label>
                        <textarea
                            id="block-reason"
                            rows={2}
                            maxLength={500}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            placeholder="e.g. Subscription unpaid since September"
                            className={`${inputClass} mt-1.5`}
                        />
                        <FieldError id="block-reason-error" message={form.errors.reason} />

                        <div className="mt-5 grid grid-cols-2 gap-2">
                            <button type="button" onClick={() => setOpen(false)} className={secondaryButton}>
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50"
                            >
                                <LuBan className="h-4 w-4" /> Block shop
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}

/** Admin shop settings: shown above the tabs while the shop is blocked, with "Unblock". */
export function BlockedBanner({ shop }) {
    const [confirm, confirmDialog] = useConfirm();
    const [busy, setBusy] = useState(false);

    async function unblock() {
        const ok = await confirm({
            title: `Unblock ${shop.name}?`,
            message: 'Its card page, menu, staff app and owner dashboard start working again straight away.',
            confirmLabel: 'Unblock',
        });
        if (!ok) return;
        router.delete(`/admin/shops/${shop.id}/block`, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    }

    return (
        <div role="status" className="mb-4 flex flex-col gap-3 rounded-2xl border border-red-500/30 bg-red-500/10 p-4 sm:flex-row sm:items-center">
            <LuBan className="hidden h-5 w-5 shrink-0 text-red-700 sm:block" />
            <div className="min-w-0 flex-1 text-sm">
                <p className="font-semibold text-red-800">Blocked since {shop.blocked_label}</p>
                {shop.blocked_reason && <p className="text-red-800/80">Reason: {shop.blocked_reason}</p>}
                <p className="text-red-800/80">Customers, staff and the owner can't use it until it's unblocked.</p>
            </div>
            <button type="button" onClick={unblock} disabled={busy} className={`${secondaryButton} shrink-0`}>
                <LuCircleCheck className="h-4 w-4 text-emerald-600" /> Unblock
            </button>
            {confirmDialog}
        </div>
    );
}
