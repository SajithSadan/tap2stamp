import QRCode from 'qrcode';

// Printable QR stickers, built entirely in the browser: the server only
// hands over codes + links (POST /admin/qr-codes/print), never renders a PDF.

// A4 portrait in millimetres: a 4 x 5 grid = 20 stickers per sheet, each a
// clean QR centred in a dashed cut box. No code is printed on the sticker
// (customers see it): the admin identifies and maps a sticker by scanning it
// while logged in (see QrRedirectController).
const PAGE_W = 210;
const PAGE_H = 297;
const MARGIN = 12;
const HEADER = 8;
const COLS = 4;
const ROWS = 5;
const PER_PAGE = COLS * ROWS;
const QR_SIZE = 38;

const qrOptions = { errorCorrectionLevel: 'M', margin: 0, width: 360 };

function fileName(title) {
    return `${title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'qr-codes'}.pdf`;
}

/**
 * Builds and downloads an A4 PDF of the given codes.
 * `onProgress(done, total)` is called as stickers are drawn, so large batches can show progress.
 */
export async function downloadQrPdf({ title, codes, onProgress }) {
    // Loaded on demand: jsPDF is only needed on the admin QR page, and only when printing.
    const { jsPDF } = await import('jspdf');
    const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });

    const cellW = (PAGE_W - MARGIN * 2) / COLS;
    const cellH = (PAGE_H - MARGIN * 2 - HEADER) / ROWS;
    const pages = Math.ceil(codes.length / PER_PAGE);

    for (let i = 0; i < codes.length; i++) {
        const slot = i % PER_PAGE;

        if (slot === 0) {
            if (i > 0) doc.addPage();

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(120);
            doc.text(title, MARGIN, MARGIN + 3);
            doc.text(`Page ${i / PER_PAGE + 1} of ${pages} · ${codes.length} codes`, PAGE_W - MARGIN, MARGIN + 3, { align: 'right' });
        }

        const x = MARGIN + (slot % COLS) * cellW;
        const y = MARGIN + HEADER + Math.floor(slot / COLS) * cellH;

        doc.setDrawColor(200);
        doc.setLineWidth(0.2);
        doc.setLineDashPattern([1, 1], 0);
        doc.rect(x, y, cellW, cellH);

        const image = await QRCode.toDataURL(codes[i].scan_url, qrOptions);
        doc.addImage(image, 'PNG', x + (cellW - QR_SIZE) / 2, y + (cellH - QR_SIZE) / 2, QR_SIZE, QR_SIZE);

        // Hand control back to the browser now and then so the progress label repaints.
        if (i % 25 === 24) {
            onProgress?.(i + 1, codes.length);
            await new Promise((resolve) => setTimeout(resolve, 0));
        }
    }

    onProgress?.(codes.length, codes.length);
    doc.save(fileName(title));
}

/** Downloads one clean QR as a PNG (named after its code) - for a quick one-off print or share. */
export async function downloadQrPng({ code, scan_url }) {
    const link = document.createElement('a');
    link.href = await QRCode.toDataURL(scan_url, { errorCorrectionLevel: 'M', margin: 2, width: 800 });
    link.download = `qr-${code}.png`;
    link.click();
}
