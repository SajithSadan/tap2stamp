import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LuX } from 'react-icons/lu';
import { FieldError, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';

const STAGES = [
    ['received', 'Order received', 'Paid, not started yet.'],
    ['processing', 'Processing', 'Being prepared.'],
    ['dispatched', 'Dispatched', 'Posted - add the tracking below. The owner is emailed.'],
    ['delivered', 'Delivered', 'Arrived. The owner is emailed.'],
];

const COURIERS = ['Royal Mail', 'Parcelforce', 'Evri', 'DPD', 'DHL', 'UPS', 'Yodel', 'Hand delivered'];

/** Admin: "Update" button + dialog to move a paid order to a stage (forwards or back). */
export default function OrderStageEditor({ order, buttonClassName = secondaryButton }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        stage: order.stage,
        courier: order.courier ?? '',
        tracking_number: order.tracking_number ?? '',
        tracking_url: order.tracking_url ?? '',
    });

    if (order.status !== 'paid') return null;

    const shipped = form.data.stage === 'dispatched' || form.data.stage === 'delivered';
    const next = STAGES[STAGES.findIndex(([key]) => key === order.stage) + 1];

    function show() {
        form.setData({
            stage: next?.[0] ?? order.stage,
            courier: order.courier ?? '',
            tracking_number: order.tracking_number ?? '',
            tracking_url: order.tracking_url ?? '',
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(e) {
        e.preventDefault();
        form.put(`/admin/orders/${order.id}/stage`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    }

    return (
        <>
            <button type="button" onClick={show} className={`${buttonClassName} whitespace-nowrap`}>
                {next ? `Update: ${next[1]}…` : 'Update'}
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={() => setOpen(false)}>
                    <form
                        onSubmit={submit}
                        noValidate
                        role="dialog"
                        aria-modal="true"
                        aria-label={`Update order #${order.id}`}
                        onClick={(e) => e.stopPropagation()}
                        className="max-h-[90dvh] w-full overflow-y-auto rounded-t-2xl border border-brand-border bg-brand-card p-6 text-left sm:max-w-md sm:rounded-2xl"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="font-heading text-lg font-semibold text-brand-text">Order #{order.id}</h2>
                                <p className="mt-0.5 text-sm text-brand-muted">
                                    {order.quantity} × {order.product_name}
                                    {order.shop_name && ` · ${order.shop_name}`}
                                </p>
                            </div>
                            <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="-m-1 rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg">
                                <LuX className="h-4 w-4" />
                            </button>
                        </div>

                        <fieldset className="mt-5 space-y-2">
                            <legend className="mb-2 text-sm font-medium text-brand-text">Stage</legend>
                            {STAGES.map(([key, label, hint]) => (
                                <label
                                    key={key}
                                    className={`flex cursor-pointer items-start gap-3 rounded-xl border px-3.5 py-2.5 transition-colors ${
                                        form.data.stage === key ? 'border-brand-accent bg-brand-accent/5' : 'border-brand-border hover:bg-brand-bg'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="stage"
                                        value={key}
                                        checked={form.data.stage === key}
                                        onChange={() => form.setData('stage', key)}
                                        className="mt-1 accent-[var(--color-brand-accent)]"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-brand-text">
                                            {label}
                                            {key === order.stage && <span className="ml-1.5 text-xs font-normal text-brand-muted">(now)</span>}
                                        </span>
                                        <span className="block text-xs text-brand-muted">{hint}</span>
                                    </span>
                                </label>
                            ))}
                            <FieldError message={form.errors.stage} />
                        </fieldset>

                        {shipped && (
                            <div className="mt-5 space-y-3">
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-medium text-brand-text">Courier</span>
                                    <input
                                        list="order-couriers"
                                        value={form.data.courier}
                                        onChange={(e) => form.setData('courier', e.target.value)}
                                        placeholder="Royal Mail"
                                        className={inputClass}
                                    />
                                    <datalist id="order-couriers">
                                        {COURIERS.map((c) => (
                                            <option key={c} value={c} />
                                        ))}
                                    </datalist>
                                    <FieldError message={form.errors.courier} />
                                </label>
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-medium text-brand-text">Tracking number</span>
                                    <input
                                        value={form.data.tracking_number}
                                        onChange={(e) => form.setData('tracking_number', e.target.value)}
                                        className={inputClass}
                                    />
                                    <FieldError message={form.errors.tracking_number} />
                                </label>
                                <label className="block">
                                    <span className="mb-1.5 block text-sm font-medium text-brand-text">Tracking link</span>
                                    <input
                                        type="url"
                                        value={form.data.tracking_url}
                                        onChange={(e) => form.setData('tracking_url', e.target.value)}
                                        placeholder="https://…"
                                        className={inputClass}
                                    />
                                    <span className="mt-1 block text-xs text-brand-muted">Paste the courier's tracking page - the owner gets a "Track parcel" button.</span>
                                    <FieldError message={form.errors.tracking_url} />
                                </label>
                            </div>
                        )}

                        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" onClick={() => setOpen(false)} className={secondaryButton}>
                                Cancel
                            </button>
                            <button type="submit" disabled={form.processing} className={primaryButton}>
                                {form.processing ? 'Saving…' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
