import QRCode from 'qrcode';
import { loadImage, prepareRenderer } from '@/lib/qrRender';
import { FONT_STACKS, blockAspect, formatSerial } from '@/lib/qrStyle';

// Printable QR stickers, built entirely in the browser: the server only
// hands over codes + links (POST /admin/qr-codes/print), never renders a PDF.
//
// Two looks:
//  - plain: a 4 x 5 grid = 20 clean QRs per A4 sheet, each in a dashed cut box;
//  - a sticker design (Admin/QrCodes/DesignEditor): the design's background
//    image at its printed size, with each code's styled QR block (lib/qrRender.js)
//    placed where the design says.
// Either look prints as a sheet (as many as fit, with cut lines) or one per
// page (centred). Designs always print at their exact size (a preset like
// Stand 9 x 14 cm, or custom) - print at 100% / "Actual size".
// No code is printed on the sticker (customers see it): the admin identifies
// and maps a sticker by scanning it while logged in (see QrRedirectController).

const PAGE_W = 210;
const PAGE_H = 297;
// 10 mm is within every printer's printable area, and fits three 6 cm table cards across A4.
const MARGIN = 10;
const HEADER = 8;
const COLS = 4;
const ROWS = 5;
const QR_SIZE = 38;
/** Space between designed stickers, for cutting. */
const GAP = 4;
/** Longest side a design background is embedded at - about 300 dpi at 200 mm. */
const MAX_BG_PX = 2400;
/** QR blocks are rendered at ~300 dpi (12 px per mm), never below this width. */
const PX_PER_MM = 12;
const MIN_BLOCK_PX = 360;
// Page-filling QRs stop here (~200 dpi at 200 mm) - still crisp, and keeps big batches' PDFs sane.
const MAX_BLOCK_PX = 1600;

const qrOptions = { errorCorrectionLevel: 'M', margin: 0, width: 360 };

function fileName(title) {
    return `${title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'qr-codes'}.pdf`;
}

/**
 * The design's background, re-encoded at print resolution: JPEG for photos,
 * PNG when the upload was a PNG (it may be transparent). Keeps big uploads
 * from bloating the PDF, and turns WebP into something jsPDF can embed.
 */
async function loadBackground(design) {
    const img = await loadImage(design.image_url);
    const scale = Math.min(1, MAX_BG_PX / Math.max(img.naturalWidth, img.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.naturalWidth * scale);
    canvas.height = Math.round(img.naturalHeight * scale);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

    const png = /\.png$/i.test(design.image_url);

    return {
        canvas,
        dataUrl: png ? canvas.toDataURL('image/png') : canvas.toDataURL('image/jpeg', 0.92),
        format: png ? 'PNG' : 'JPEG',
    };
}

/**
 * The sticker's exact printed size (mm): width_mm x height_mm (a preset
 * like Stand 90 x 140, or custom), or - with no height - the artwork's own
 * shape at width_mm.
 */
export function stickerSize(design) {
    const aspect = design.image_height / design.image_width;
    return { w: design.width_mm, h: design.height_mm || design.width_mm * aspect };
}

/**
 * Where the artwork sits inside a w x h sticker: fitted inside and centred,
 * never stretched (a sticker shaped differently from the artwork gets plain
 * edges). The QR's saved spot is relative to this rectangle. Mirrors
 * QrDesign::artWidthMm() on the server.
 */
export function artRect(w, h, imageAspect) {
    const aw = Math.min(w, h / imageAspect);
    const ah = aw * imageAspect;
    return { x: (w - aw) / 2, y: (h - ah) / 2, w: aw, h: ah };
}

/** How far the artwork's shape is from the sticker's (0 = same shape). */
export function shapeMismatch(design) {
    const { w, h } = stickerSize(design);
    const art = artRect(w, h, design.image_height / design.image_width);
    return 1 - (art.w * art.h) / (w * h);
}

/**
 * How stickers tile on an A4 sheet, at their exact size (scaled down only
 * in the rare case one wouldn't fit the page at all).
 */
export function designLayout(design) {
    const usableW = PAGE_W - MARGIN * 2;
    const usableH = PAGE_H - MARGIN * 2 - HEADER;

    let { w, h } = stickerSize(design);
    const fit = Math.min(1, usableW / w, usableH / h);
    w *= fit;
    h *= fit;

    const cols = Math.max(1, Math.floor((usableW + GAP) / (w + GAP)));
    const rows = Math.max(1, Math.floor((usableH + GAP) / (h + GAP)));

    return { w, h, cols, rows, perPage: cols * rows, offsetX: (usableW - (cols * w + (cols - 1) * GAP)) / 2 };
}

/** A plain QR printed one per (A4) page. */
const BIG_QR = 140;

function pageHeader(doc, title, page, pages, count) {
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(120);
    doc.text(title, MARGIN, MARGIN + 3);
    doc.text(`Page ${page} of ${pages} · ${count} codes`, PAGE_W - MARGIN, MARGIN + 3, { align: 'right' });
}

function cutBox(doc, x, y, w, h) {
    doc.setDrawColor(200);
    doc.setLineWidth(0.2);
    doc.setLineDashPattern([1, 1], 0);
    doc.rect(x, y, w, h);
}

/**
 * Where each sticker goes: `perPage` and `rect(slot)` -> { x, y, w, h } in mm.
 * Sheets tile stickers under a small header with cut lines; one-per-page
 * centres a single sticker at its exact size (cut lines round a design).
 */
function sheetPlan(design, onePerPage) {
    if (onePerPage && design) {
        // The page IS the sticker: exactly its size, artwork edge to edge, nothing else on it.
        const { w, h } = stickerSize(design);
        return { perPage: 1, header: false, cutLines: false, page: [w, h], rect: () => ({ x: 0, y: 0, w, h }) };
    }
    if (onePerPage) {
        return { perPage: 1, header: false, cutLines: false, rect: () => ({ x: (PAGE_W - BIG_QR) / 2, y: (PAGE_H - BIG_QR) / 2, w: BIG_QR, h: BIG_QR }) };
    }

    if (design) {
        const layout = designLayout(design);
        return {
            perPage: layout.perPage,
            header: true,
            cutLines: true,
            rect: (slot) => ({
                x: MARGIN + layout.offsetX + (slot % layout.cols) * (layout.w + GAP),
                y: MARGIN + HEADER + Math.floor(slot / layout.cols) * (layout.h + GAP),
                w: layout.w,
                h: layout.h,
            }),
        };
    }

    const cellW = (PAGE_W - MARGIN * 2) / COLS;
    const cellH = (PAGE_H - MARGIN * 2 - HEADER) / ROWS;
    return {
        perPage: COLS * ROWS,
        header: true,
        cutLines: true,
        rect: (slot) => ({ x: MARGIN + (slot % COLS) * cellW, y: MARGIN + HEADER + Math.floor(slot / COLS) * cellH, w: cellW, h: cellH }),
    };
}

/** Everything needed to draw one side (front or back) with a design, loaded once per print. */
async function prepareSide(design, alias) {
    if (!design) return null;
    return {
        design,
        alias,
        background: await loadBackground(design),
        render: await prepareRenderer(design.style, design.logo_url),
        block: blockAspect(design.style),
        imageAspect: design.image_height / design.image_width,
    };
}

/** Draws one sticker (a design side, or a plain QR) for `code` into the rectangle. */
async function drawSticker(doc, side, { x, y, w, h }, code, plainSize, plainPx) {
    if (!side) {
        const qr = await QRCode.toDataURL(code.scan_url, { ...qrOptions, width: plainPx });
        doc.addImage(qr, 'PNG', x + (w - plainSize) / 2, y + (h - plainSize) / 2, plainSize, plainSize);
        return;
    }

    const { design, render, block, background } = side;
    // The artwork, fitted inside the sticker; the QR's spot is relative to it.
    const art = artRect(w, h, side.imageAspect);
    const ax = x + art.x;
    const ay = y + art.y;
    const size = design.qr_size * art.w;
    // Rendered at ~300 dpi for its printed size.
    const blockPx = Math.min(MAX_BLOCK_PX, Math.max(MIN_BLOCK_PX, Math.round(size * PX_PER_MM)));

    // Same alias every time: jsPDF embeds each side's background once and reuses it.
    doc.addImage(background.dataUrl, background.format, ax, ay, art.w, art.h, side.alias, 'FAST');
    const qr = render(code.scan_url, blockPx).toDataURL('image/png');
    doc.addImage(qr, 'PNG', ax + design.qr_x * art.w, ay + design.qr_y * art.h, size, size * block);

    if (design.style.serial_enabled) drawSerial(doc, design.style, art, ax, ay, code.serial);
}

/** PDF fonts matching the design's font choice (Modern / Classic / Mono). */
const PDF_FONTS = { sans: 'helvetica', serif: 'times', mono: 'courier' };
const MM_TO_PT = 72 / 25.4;

/** The serial number (001 …) as crisp text, bottom centre of the artwork. */
function drawSerial(doc, style, art, ax, ay, serial) {
    const heightMm = style.serial_size * art.w;
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(style.serial_color.slice(i, i + 2), 16));

    doc.setFont(PDF_FONTS[style.font] ?? 'helvetica', style.serial_bold ? 'bold' : 'normal');
    doc.setFontSize(heightMm * MM_TO_PT);
    doc.setTextColor(r, g, b);
    // Baseline sits `serial_offset` of the artwork's height above its bottom edge.
    doc.text(formatSerial(serial, style), ax + art.w / 2, ay + art.h * (1 - style.serial_offset), { align: 'center', baseline: 'bottom' });
}

/** Whether a design can be the back of `front`: backs must be the same printed size, so they line up. */
export function canBeBackOf(front, back) {
    if (!front || !back) return false;
    const a = stickerSize(front);
    const b = stickerSize(back);
    return Math.round(a.w) === Math.round(b.w) && Math.round(a.h) === Math.round(b.h);
}

/**
 * Builds a PDF (jsPDF document) of the given codes, plain or on a sticker
 * design, as a sheet of stickers or one per page (`onePerPage`). Stickers
 * print at their exact size - print at 100% / "Actual size".
 *
 * `doubleSided`: every page is followed by its back page, with the same
 * codes' QRs on `backDesign` (null = the same design as the front). On a
 * sheet the back page is mirrored left-to-right, so after a long-edge flip
 * each back lands behind its own front.
 *
 * `onProgress(done, total)` is called as stickers are drawn, so large batches can show progress.
 */
async function buildQrPdf({ title, codes, design = null, onePerPage = false, doubleSided = false, backDesign = null, onProgress }) {
    // Loaded on demand: jsPDF is only needed on the admin QR page, and only when printing.
    const { jsPDF } = await import('jspdf');
    const plan = sheetPlan(design, onePerPage);
    // A4, or - one design per page - pages cut to the sticker's exact size.
    const format = plan.page ?? 'a4';
    const orientation = plan.page && plan.page[0] > plan.page[1] ? 'landscape' : 'portrait';
    const doc = new jsPDF({ unit: 'mm', format, orientation, compress: true });
    const pageW = doc.internal.pageSize.getWidth();

    const front = await prepareSide(design, 'qr-design-front');
    // A different back only if it's the same size (so it lines up); otherwise the front again.
    const back = doubleSided ? (backDesign && canBeBackOf(design, backDesign) ? await prepareSide(backDesign, 'qr-design-back') : front) : null;
    const pages = Math.ceil(codes.length / plan.perPage);
    // Plain QR on its own page is printed big; in a sheet cell it's QR_SIZE.
    const plainSize = onePerPage ? BIG_QR : QR_SIZE;
    const plainPx = onePerPage ? 1200 : qrOptions.width;
    let done = 0;

    for (let page = 0; page < pages; page++) {
        const onPage = codes.slice(page * plan.perPage, (page + 1) * plan.perPage);

        // Front.
        if (page > 0) doc.addPage(format, orientation);
        if (plan.header) pageHeader(doc, title, page + 1, pages, codes.length);
        for (let slot = 0; slot < onPage.length; slot++) {
            const rect = plan.rect(slot);
            await drawSticker(doc, front, rect, onPage[slot], plainSize, plainPx);
            if (plan.cutLines) cutBox(doc, rect.x, rect.y, rect.w, rect.h);
        }

        // Back: the same codes, mirrored left-to-right for a long-edge flip.
        if (doubleSided) {
            doc.addPage(format, orientation);
            for (let slot = 0; slot < onPage.length; slot++) {
                const rect = plan.rect(slot);
                await drawSticker(doc, back, { ...rect, x: pageW - rect.x - rect.w }, onPage[slot], plainSize, plainPx);
            }
        }

        // Hand control back to the browser after each page so the progress label repaints.
        done += onPage.length;
        onProgress?.(done, codes.length);
        await new Promise((resolve) => setTimeout(resolve, 0));
    }

    return doc;
}

/**
 * Opens a blank tab straight away - it has to happen in the click itself,
 * or the browser's popup blocker stops it - to show the print view in once
 * the PDF is ready. Returns null if popups are blocked.
 */
export function openPrintWindow() {
    const win = window.open('', '_blank');
    if (win) {
        win.document.title = 'Preparing your print…';
        win.document.body.innerHTML =
            '<p style="font:16px system-ui,sans-serif;color:#5e6e7f;text-align:center;margin-top:40vh">Preparing your print…</p>';
    }
    return win;
}

/**
 * Builds the PDF and shows it in `win` (from openPrintWindow) with the print
 * dialog opening by itself. If there's no window (popups blocked), it
 * downloads the PDF instead, so nothing is lost.
 */
export async function printQrPdf(options, win) {
    let doc;
    try {
        doc = await buildQrPdf(options);
    } catch (error) {
        win?.close();
        throw error;
    }

    if (!win || win.closed) {
        doc.save(fileName(options.title));
        return;
    }
    doc.autoPrint();
    win.location.href = doc.output('bloburl');
}

/**
 * Downloads one QR as a PNG (named after its code) - for a quick one-off print
 * or share. With a design, it's the full sticker: background + QR in place.
 */
export async function downloadQrPng(qr, design = null) {
    const link = document.createElement('a');
    link.href = await qrPngDataUrl(qr, design);
    link.download = `qr-${qr.code}.png`;
    link.click();
}

/** The PNG downloadQrPng() saves, as a data URL - also used to preview it on the page. */
export async function qrPngDataUrl({ scan_url, serial }, design = null) {
    let href;

    if (design) {
        const { canvas } = await loadBackground(design);
        const ctx = canvas.getContext('2d');
        const render = await prepareRenderer(design.style, design.logo_url);
        const size = Math.round(design.qr_size * canvas.width);
        const qr = render(scan_url, Math.max(size, MIN_BLOCK_PX));
        ctx.drawImage(qr, Math.round(design.qr_x * canvas.width), Math.round(design.qr_y * canvas.height), size, size * blockAspect(design.style));

        const { style } = design;
        if (style.serial_enabled) {
            // Same placement as the PDF: bottom centre, baseline serial_offset of the height up.
            ctx.font = `${style.serial_bold ? 700 : 400} ${style.serial_size * canvas.width}px ${FONT_STACKS[style.font] ?? FONT_STACKS.sans}`;
            ctx.fillStyle = style.serial_color;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(formatSerial(serial, style), canvas.width / 2, canvas.height * (1 - style.serial_offset));
        }
        href = canvas.toDataURL('image/png');
    } else {
        href = await QRCode.toDataURL(scan_url, { errorCorrectionLevel: 'M', margin: 2, width: 800 });
    }

    return href;
}
