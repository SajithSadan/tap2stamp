import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { FaWhatsapp } from 'react-icons/fa6';
import { LuExternalLink, LuX } from 'react-icons/lu';
import { CopyButton, secondaryButton } from '@/Components/Dashboard/Ui';

/** A mouse and a big screen: show the QR to scan with the phone instead of opening WhatsApp here. */
const isDesktop = () => typeof window !== 'undefined' && window.matchMedia?.('(hover: hover) and (pointer: fine)').matches;

/** "+447700900123" from "447700900123" - for reading; the link uses the digits. */
const pretty = (number) => `+${number}`;

/**
 * Admin: message a shop / owner on WhatsApp. `number` is the full
 * international number, digits only (`App\Support\WhatsApp::number()`).
 * On a phone it opens WhatsApp straight away; on a desktop it shows a QR of
 * the same wa.me link - scan it with the phone's camera to open the chat there
 * - plus "Open on this computer" for WhatsApp Web / Desktop.
 * `text`: a labelled button (page headers) instead of the grid's icon.
 */
export default function WhatsAppButton({ number, name, text }) {
    const [open, setOpen] = useState(false);
    const [qr, setQr] = useState(null);
    const link = `https://wa.me/${number}`;

    useEffect(() => {
        if (!open) return;
        QRCode.toDataURL(link, { margin: 1, width: 240 })
            .then(setQr)
            .catch(() => setQr(null));
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, link]);

    if (!number) return null;

    const label = `WhatsApp ${name ?? pretty(number)}`;
    const buttonClass = text
        ? secondaryButton
        : 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#25D366] transition-colors hover:bg-[#25D366]/10';

    return (
        <>
            <a
                href={link}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                title={label}
                onClick={(e) => {
                    if (!isDesktop()) return;
                    e.preventDefault();
                    setOpen(true);
                }}
                className={buttonClass}
            >
                <FaWhatsapp className={`h-4 w-4 ${text ? 'text-[#25D366]' : ''}`} /> {text}
            </a>

            {open &&
                createPortal(
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/50 p-4 backdrop-blur-sm" onClick={() => setOpen(false)}>
                        <div
                            role="dialog"
                            aria-modal="true"
                            aria-label={label}
                            onClick={(e) => e.stopPropagation()}
                            className="w-full max-w-xs rounded-2xl bg-brand-card p-5 text-center shadow-xl"
                        >
                            <div className="flex items-start justify-between gap-3 text-left">
                                <div className="min-w-0">
                                    <h2 className="flex items-center gap-2 font-heading text-lg font-semibold text-brand-text">
                                        <FaWhatsapp className="h-5 w-5 shrink-0 text-[#25D366]" />
                                        <span className="truncate">{name ?? 'WhatsApp'}</span>
                                    </h2>
                                    <p className="mt-0.5 flex items-center gap-1 text-sm tabular-nums text-brand-muted">
                                        {pretty(number)}
                                        <CopyButton text={pretty(number)} label="Copy number" />
                                    </p>
                                </div>
                                <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                                    <LuX className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="mx-auto mt-3 flex h-52 w-52 items-center justify-center rounded-xl bg-white p-2 ring-1 ring-brand-border">
                                {qr && <img src={qr} alt={`QR code to open a WhatsApp chat with ${pretty(number)}`} className="h-full w-full" />}
                            </div>
                            <p className="mt-3 text-xs text-brand-muted">Scan with your phone's camera to open the chat in WhatsApp.</p>

                            <a href={link} target="_blank" rel="noopener noreferrer" className={`${secondaryButton} mt-4 w-full`}>
                                <LuExternalLink className="h-4 w-4" /> Open on this computer
                            </a>
                        </div>
                    </div>,
                    document.body,
                )}
        </>
    );
}
