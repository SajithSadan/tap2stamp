import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LuArrowLeft, LuCheck, LuCircleCheck, LuCircleDashed, LuCopy, LuExternalLink, LuLink, LuNfc, LuQrCode, LuScanLine, LuUnlink } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import AdminLayout from '@/Components/Dashboard/AdminLayout';
import QrDestinationField from '@/Components/Dashboard/QrDestinationField';
import { CopyButton, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import QrStickerScanner from '@/Components/QrStickerScanner';

/**
 * Copies to the clipboard. navigator.clipboard needs https; the textarea
 * fallback also works over plain http (e.g. testing on a phone via the LAN IP).
 */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        return ok;
    }
}

/**
 * The sticker's own permanent link, to write onto an NFC tag with an
 * external app (e.g. NFC Tools). Not the destination: like the QR, the tag
 * then follows any later remap without being rewritten.
 */
function NfcLink({ url }) {
    const [state, setState] = useState(null); // null | 'copied' | 'failed'

    async function copy() {
        setState((await copyText(url)) ? 'copied' : 'failed');
        setTimeout(() => setState(null), 2000);
    }

    return (
        <div className="border-t border-brand-border px-5 py-4">
            <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-brand-muted">
                <LuNfc className="h-3.5 w-3.5" /> Link for an NFC tag
            </p>
            {/* Selectable by hand too, in case copying is blocked. */}
            <p className="mt-1.5 select-all break-all rounded-lg bg-brand-bg px-3 py-2 font-mono text-sm text-brand-text">{url}</p>
            <button type="button" onClick={copy} className={`${secondaryButton} mt-3 w-full py-3`} aria-live="polite">
                {state === 'copied' ? (
                    <>
                        <LuCheck className="h-4 w-4 text-emerald-600" /> Copied
                    </>
                ) : (
                    <>
                        <LuCopy className="h-4 w-4" /> {state === 'failed' ? "Couldn't copy - select the link above" : 'Copy link'}
                    </>
                )}
            </button>
            <p className="mt-2 text-xs text-brand-muted">
                Write this onto the NFC tag as a URL record. It always follows this sticker, so remapping it later updates the tag too.
            </p>
        </div>
    );
}

/**
 * What a logged-in admin sees after scanning a sticker (with the phone camera
 * or the in-app scanner - GET /qr/{code}). Customers scanning the same sticker
 * are redirected, or get "Nothing found" - never this page.
 */
export default function Scan({ qr, shops }) {
    const form = useForm({ destination_url: qr.destination_url ?? '' });
    const mapped = !!qr.destination_url;
    const [confirm, confirmDialog] = useConfirm();
    // Map a roll of stickers in a row: scan the next one straight from here.
    const [scanning, setScanning] = useState(false);

    function save(e) {
        e.preventDefault();
        form.put(`/admin/qr-codes/${qr.id}`, { preserveScroll: true });
    }

    async function unmap() {
        const ok = await confirm({
            title: 'Unmap this sticker?',
            message: 'Anyone scanning it will see “Nothing found” until it’s mapped again.',
            confirmLabel: 'Unmap',
            danger: true,
        });
        if (!ok) return;

        router.put(`/admin/qr-codes/${qr.id}`, { destination_url: '' }, { preserveScroll: true, onSuccess: () => form.setData('destination_url', '') });
    }

    const scanNext = (
        <button type="button" onClick={() => setScanning(true)} className={primaryButton}>
            <LuScanLine className="h-4 w-4" /> Scan next sticker
        </button>
    );

    return (
        <AdminLayout
            title={mapped ? 'Sticker mapped' : 'Map this sticker'}
            description="Only you see this screen. Customers who scan the sticker go straight to its destination."
            // Desktop: in the header. Phones: full width under the card (and the footer's Scan button).
            actions={<div className="hidden lg:block">{scanNext}</div>}
        >
            <div className="max-w-xl">
                <section className="overflow-hidden rounded-2xl border border-brand-border bg-brand-card">
                    {/* The sticker: code, batch and status. */}
                    <div className="flex items-center gap-4 px-5 py-5">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-bg text-brand-text ring-1 ring-brand-border">
                            <LuQrCode className="h-6 w-6" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-mono text-2xl font-bold tracking-wider text-brand-text">{qr.code}</p>
                            <p className="truncate text-xs text-brand-muted">{qr.batch_label}</p>
                        </div>
                        {mapped ? (
                            <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                <LuCircleCheck className="h-3.5 w-3.5" /> Mapped
                            </span>
                        ) : (
                            <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                <LuCircleDashed className="h-3.5 w-3.5" /> Not mapped
                            </span>
                        )}
                    </div>

                    {/* Where it goes now. */}
                    {mapped && (
                        <div className="border-t border-brand-border px-5 py-4">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-brand-muted">
                                Sends people to{qr.mapped_at && <span className="font-normal normal-case tracking-normal"> · mapped {qr.mapped_at}</span>}
                            </p>
                            <div className="mt-1.5 flex items-center gap-2">
                                <LuLink className="h-4 w-4 shrink-0 text-emerald-600" />
                                <p className="min-w-0 flex-1 truncate text-sm font-medium text-brand-text" title={qr.destination_url}>
                                    {qr.destination_url}
                                </p>
                                <CopyButton text={qr.destination_url} label="Copy destination link" />
                                <a
                                    href={qr.destination_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Open destination in a new tab"
                                    className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg hover:text-brand-text"
                                >
                                    <LuExternalLink className="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    )}

                    {mapped && <NfcLink url={qr.scan_url} />}

                    {/* Set or change the destination. */}
                    <form onSubmit={save} noValidate className="space-y-3 border-t border-brand-border px-5 py-5">
                        <QrDestinationField
                            label={mapped ? 'Change destination' : 'Send people to'}
                            value={form.data.destination_url}
                            onChange={(url) => form.setData('destination_url', url)}
                            shops={shops}
                            error={form.errors.destination_url}
                        />

                        <button type="submit" disabled={form.processing} className={`${primaryButton} w-full py-3`}>
                            <LuLink className="h-4 w-4" /> {form.processing ? 'Saving…' : mapped ? 'Update destination' : 'Map sticker'}
                        </button>

                        {mapped && (
                            <button
                                type="button"
                                onClick={unmap}
                                className="mx-auto flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                <LuUnlink className="h-4 w-4" /> Unmap sticker
                            </button>
                        )}
                    </form>
                </section>

                {/* Phones: the next step, full width and easy to reach with a thumb. */}
                <div className="mt-4 lg:hidden [&>button]:w-full [&>button]:py-3">{scanNext}</div>

                <Link href="/admin/qr-codes" className={`${secondaryButton} mt-3 w-full border-transparent bg-transparent lg:w-auto`}>
                    <LuArrowLeft className="h-4 w-4" /> All QR codes
                </Link>
            </div>

            {confirmDialog}
            {scanning && <QrStickerScanner onClose={() => setScanning(false)} />}
        </AdminLayout>
    );
}
