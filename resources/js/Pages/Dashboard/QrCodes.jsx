import { useEffect, useState } from "react";
import { LuDownload, LuFileText, LuLoaderCircle, LuPrinter, LuQrCode } from "react-icons/lu";
import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import { EmptyState, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";
import { downloadQrPng, printQrPdf, qrPngDataUrl, stickerSize } from "@/lib/qrPrint";

/**
 * Overseas shops (no counter display outside the UK): the QR codes the admin
 * mapped to this shop, in the design the admin picked for it, to download
 * and print. Drawn in the browser by the same renderer as the admin's
 * prints, so what's shown here is exactly what prints.
 */

function QrCard({ qr, design, shopName }) {
    const [preview, setPreview] = useState(null);
    const [failed, setFailed] = useState(false);
    const [busy, setBusy] = useState(null); // "png" | "pdf"

    useEffect(() => {
        let live = true;
        qrPngDataUrl(qr, design)
            .then((url) => live && setPreview(url))
            .catch(() => live && setFailed(true));
        return () => {
            live = false;
        };
    }, [qr.id, design?.id]);

    async function run(kind, task) {
        setBusy(kind);
        try {
            await task();
        } finally {
            setBusy(null);
        }
    }

    // One sticker-size page (or one big plain QR on A4); no window, so it downloads.
    const pdf = () => printQrPdf({ title: `${shopName} QR ${qr.code}`, codes: [qr], design, onePerPage: true }, null);

    return (
        <li className="flex flex-col overflow-hidden rounded-2xl border border-brand-border bg-brand-card">
            <div className="flex aspect-square items-center justify-center bg-brand-bg p-4">
                {preview ? (
                    <img src={preview} alt={`QR code ${qr.code}`} className="max-h-full max-w-full rounded-lg object-contain" />
                ) : failed ? (
                    <p className="text-sm text-brand-muted">Couldn't show this one - try downloading it.</p>
                ) : (
                    <LuLoaderCircle className="h-6 w-6 animate-spin text-brand-muted" aria-label="Preparing" />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-3 p-4">
                <p className="text-sm text-brand-muted">
                    Opens: <span className="font-medium text-brand-text">{qr.opens ?? "Not set up yet"}</span>
                </p>
                <div className="mt-auto grid grid-cols-2 gap-2">
                    <button type="button" disabled={busy !== null} onClick={() => run("png", () => downloadQrPng(qr, design))} className={secondaryButton}>
                        {busy === "png" ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuDownload className="h-4 w-4" />} PNG
                    </button>
                    <button type="button" disabled={busy !== null} onClick={() => run("pdf", pdf)} className={secondaryButton}>
                        {busy === "pdf" ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuFileText className="h-4 w-4" />} PDF
                    </button>
                </div>
            </div>
        </li>
    );
}

export default function QrCodes({ shop, codes, design }) {
    const [allBusy, setAllBusy] = useState(false);
    const size = design ? stickerSize(design) : null;

    async function downloadAll() {
        setAllBusy(true);
        try {
            await printQrPdf({ title: `${shop.name} QR codes`, codes, design, onePerPage: true }, null);
        } finally {
            setAllBusy(false);
        }
    }

    return (
        <OwnerLayout
            shop={shop}
            title="QR codes"
            description="Print your QR code and put it where customers can scan it to get their card."
            actions={
                codes.length > 1 && (
                    <button type="button" onClick={downloadAll} disabled={allBusy} className={primaryButton}>
                        {allBusy ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuDownload className="h-4 w-4" />} Download all (PDF)
                    </button>
                )
            }
        >
            {codes.length === 0 ? (
                <Panel>
                    <EmptyState icon={LuQrCode} title="Your QR code is on its way">
                        We're setting it up for you. It'll appear here, ready to download and print.
                    </EmptyState>
                </Panel>
            ) : (
                <>
                    <p className="mb-4 flex items-start gap-2 text-sm text-brand-muted">
                        <LuPrinter className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            {size ? `Prints at ${Math.round(size.w)} × ${Math.round(size.h)} mm. ` : ""}
                            Print the PDF at 100% ("Actual size") so it comes out the right size.
                        </span>
                    </p>
                    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {codes.map((qr) => (
                            <QrCard key={qr.id} qr={qr} design={design} shopName={shop.name} />
                        ))}
                    </ul>
                </>
            )}
        </OwnerLayout>
    );
}
