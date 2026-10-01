import { router } from '@inertiajs/react';
import { useConfirm } from '@/Components/ConfirmDialog';
import { primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import { formatPence } from '@/lib/money';

/** Admin: "Confirm payment" / "Cancel" on an order arranged with a shop that's waiting for its money. */
export default function OrderPaymentActions({ order, compact = false }) {
    const [confirm, confirmDialog] = useConfirm();

    if (!order.awaiting_payment) return null;

    const size = compact ? '!px-2.5 !py-1.5 text-xs' : '';
    const how = order.payment_method === 'cash' ? 'cash' : 'bank transfer';

    async function confirmPaid() {
        const ok = await confirm({
            title: `Payment received for #${order.id}?`,
            message: `${formatPence(order.total_pence)} by ${how} (reference ${order.reference}). The order then moves to “Order received”.`,
            confirmLabel: 'Yes, received',
        });
        if (ok) router.put(`/admin/orders/${order.id}/paid`, { payment_method: order.payment_method }, { preserveScroll: true });
    }

    async function cancel() {
        const ok = await confirm({
            title: `Cancel order #${order.id}?`,
            message: 'Use this if the payment never came or the shop changed its mind. It stays in the list as cancelled.',
            confirmLabel: 'Cancel order',
            danger: true,
        });
        if (ok) router.put(`/admin/orders/${order.id}/cancel`, {}, { preserveScroll: true });
    }

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            <button type="button" onClick={confirmPaid} className={`${primaryButton} ${size} whitespace-nowrap`}>
                Confirm payment
            </button>
            <button type="button" onClick={cancel} className={`${secondaryButton} ${size}`}>
                Cancel
            </button>
            {confirmDialog}
        </span>
    );
}
