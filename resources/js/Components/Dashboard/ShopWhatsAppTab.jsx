import { router } from "@inertiajs/react";
import { FaWhatsapp } from "react-icons/fa6";
import { LuTrash2 } from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import { Panel, secondaryButton } from "@/Components/Dashboard/Ui";

const MODE_LABEL = { new: "New account (Meta's window)", existing: "Owner's WhatsApp Business app", keys: "Owner's own Meta keys" };

/**
 * Admin → shop settings → WhatsApp: the shop's own number for offers. Only
 * the owner connects one (they fill in Meta's forms, on their WhatsApp
 * page); the admin can see it and remove it.
 */
export default function ShopWhatsAppTab({ shop, account }) {
    const [confirm, confirmDialog] = useConfirm();

    async function remove() {
        const ok = await confirm({
            title: "Remove this shop's WhatsApp number?",
            message: "The shop can't send offers until the owner connects a number again.",
            confirmLabel: "Remove",
            danger: true,
        });
        if (ok) router.delete(`/admin/shops/${shop.id}/whatsapp`, { preserveScroll: true });
    }

    return (
        <Panel title="WhatsApp number" description="The shop's offers go out from its own number. The owner connects it on their WhatsApp page.">
            {confirmDialog}
            {account ? (
                <div className="flex flex-wrap items-center gap-3">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#25D366]/15 text-[#128C7E]">
                        <FaWhatsapp className="h-5 w-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-brand-text">{[account.phone, account.name].filter(Boolean).join(" · ") || "Connected"}</p>
                        <p className="text-xs text-brand-muted">
                            {MODE_LABEL[account.mode] ?? account.mode} · since {account.connected_at}
                        </p>
                        <p className="mt-1 font-mono text-[11px] text-brand-muted">
                            Account {account.waba_id} · Number ID {account.phone_number_id}
                        </p>
                    </div>
                    <button type="button" onClick={remove} className={secondaryButton}>
                        <LuTrash2 className="h-4 w-4" /> Remove
                    </button>
                </div>
            ) : (
                <p className="flex items-center gap-2 text-sm text-brand-muted">
                    <FaWhatsapp className="h-4 w-4" /> Not connected yet. Offers can't be sent until the owner connects a number.
                </p>
            )}
        </Panel>
    );
}
