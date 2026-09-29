import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import { FONT_STACKS, blockAspect, captionBand, effectiveEcc, qrFraction } from '@/lib/qrStyle';

// Draws a styled QR "block" (frame, quiet zone, dots, corner eyes, centre
// text/logo, caption) onto a canvas. The one renderer behind the design
// editor's preview, the saved-design thumbnails, the PDF and the PNG - so
// what you see while editing is exactly what prints.

const EYE = 7;

export function loadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = url;
    });
}

/** Waits for the web font (Poppins) so text isn't drawn in a fallback font. */
export async function ensureFonts(style) {
    if (style.font !== 'sans' || !document.fonts?.load) return;
    try {
        await Promise.all([document.fonts.load('700 32px Poppins'), document.fonts.load('500 32px Poppins')]);
    } catch {
        // Fall back to the next font in the stack.
    }
}

function roundRectPath(ctx, x, y, w, h, r) {
    const radius = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius);
    ctx.closePath();
}

function fillRound(ctx, x, y, w, h, r) {
    ctx.beginPath();
    roundRectPath(ctx, x, y, w, h, r);
    ctx.fill();
}

/** Largest font size (<= size) at which `text` fits in maxWidth. */
function fitFont(ctx, text, size, maxWidth, weight, family) {
    let fs = size;
    ctx.font = `${weight} ${fs}px ${family}`;
    const width = ctx.measureText(text).width;
    if (width > maxWidth) {
        fs = Math.max(6, (fs * maxWidth) / width);
        ctx.font = `${weight} ${fs}px ${family}`;
    }
    return fs;
}

/** One finder "eye": a 7x7 ring plus a 3x3 centre, in the chosen shape. */
function drawEye(ctx, x, y, m, shape) {
    const outer = EYE * m;
    ctx.beginPath();
    if (shape === 'circle') {
        ctx.arc(x + outer / 2, y + outer / 2, outer / 2, 0, Math.PI * 2);
        ctx.moveTo(x + outer / 2 + 2.5 * m, y + outer / 2);
        ctx.arc(x + outer / 2, y + outer / 2, 2.5 * m, 0, Math.PI * 2, true);
    } else {
        const r = shape === 'rounded' ? 2 * m : 0;
        roundRectPath(ctx, x, y, outer, outer, r);
        // Inner edge drawn the other way round so it cuts a hole (non-zero winding).
        const i = m;
        const s = 5 * m;
        const ir = shape === 'rounded' ? 1.3 * m : 0;
        ctx.moveTo(x + i + ir, y + i);
        ctx.arcTo(x + i, y + i, x + i, y + i + s, ir);
        ctx.arcTo(x + i, y + i + s, x + i + s, y + i + s, ir);
        ctx.arcTo(x + i + s, y + i + s, x + i + s, y + i, ir);
        ctx.arcTo(x + i + s, y + i, x + i, y + i, ir);
        ctx.closePath();
    }
    ctx.fill();

    const c = 2 * m;
    if (shape === 'circle') {
        ctx.beginPath();
        ctx.arc(x + outer / 2, y + outer / 2, 1.5 * m, 0, Math.PI * 2);
        ctx.fill();
    } else {
        fillRound(ctx, x + c, y + c, 3 * m, 3 * m, shape === 'rounded' ? 0.9 * m : 0);
    }
}

/**
 * Renders the block for `text` at `width` px wide (height follows the style).
 * `logo` is a loaded <img> for a logo centre, or null.
 */
export function renderQrBlock({ text, style, width, logo = null }) {
    const W = Math.round(width);
    const H = Math.round(W * blockAspect(style));
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    const family = FONT_STACKS[style.font] ?? FONT_STACKS.sans;

    const border = style.border_width * W;
    const pad = style.padding * W;
    const inset = border + pad;
    const band = captionBand(style) * W;
    const above = style.caption_position === 'above';
    // The QR stays square: the largest that fits the space left for it, centred in that space
    // (a custom box can be wider or taller than the QR).
    const q = W * qrFraction(style);
    const regionTop = inset + (above ? band : 0);
    const regionH = H - 2 * inset - band;
    const left = (W - q) / 2;
    const top = regionTop + (regionH - q) / 2;

    // Frame: background card, then the border drawn just inside the edge.
    const radius = style.radius * W;
    if (!style.transparent) {
        ctx.fillStyle = style.bg;
        fillRound(ctx, 0, 0, W, H, radius);
    }
    if (border > 0) {
        ctx.strokeStyle = style.border_color;
        ctx.lineWidth = border;
        ctx.beginPath();
        roundRectPath(ctx, border / 2, border / 2, W - border, H - border, Math.max(0, radius - border / 2));
        ctx.stroke();
    }

    const { modules } = QRCode.create(text, { errorCorrectionLevel: effectiveEcc(style) });
    const n = modules.size;
    const m = q / n;

    // Centre box (text or logo), in px - modules under it are left out for a clean edge.
    let box = null;
    if (style.center_type === 'text' && style.center_text.trim()) {
        const h = style.center_size * q * 0.62;
        const fs = fitFont(ctx, style.center_text, h * 0.55, q * 0.6 - h * 0.6, 700, family);
        const w = Math.min(q * 0.6, ctx.measureText(style.center_text).width + h * 0.6);
        box = { w, h, fs };
    } else if (style.center_type === 'logo') {
        const s = style.center_size * q;
        box = { w: s, h: s };
    }
    if (box) {
        box.x = left + (q - box.w) / 2;
        box.y = top + (q - box.h) / 2;
    }
    const covered = (r, c) => {
        if (!box) return false;
        const cx = left + (c + 0.5) * m;
        const cy = top + (r + 0.5) * m;
        return cx > box.x - m * 0.5 && cx < box.x + box.w + m * 0.5 && cy > box.y - m * 0.5 && cy < box.y + box.h + m * 0.5;
    };
    const inEye = (r, c) => (r < EYE && c < EYE) || (r < EYE && c >= n - EYE) || (r >= n - EYE && c < EYE);

    // Data dots.
    ctx.fillStyle = style.fg;
    for (let r = 0; r < n; r++) {
        for (let c = 0; c < n; c++) {
            if (!modules.get(r, c) || inEye(r, c) || covered(r, c)) continue;
            const x = left + c * m;
            const y = top + r * m;
            if (style.modules === 'dots') {
                ctx.beginPath();
                ctx.arc(x + m / 2, y + m / 2, m * 0.42, 0, Math.PI * 2);
                ctx.fill();
            } else if (style.modules === 'rounded') {
                fillRound(ctx, x + m * 0.04, y + m * 0.04, m * 0.92, m * 0.92, m * 0.32);
            } else {
                // A hair oversized so neighbouring squares don't show seams.
                ctx.fillRect(x, y, m + 0.4, m + 0.4);
            }
        }
    }

    // Corner eyes.
    ctx.fillStyle = style.eye_color;
    drawEye(ctx, left, top, m, style.eyes);
    drawEye(ctx, left + (n - EYE) * m, top, m, style.eyes);
    drawEye(ctx, left, top + (n - EYE) * m, m, style.eyes);

    // Centre text or logo on its own little card.
    if (box) {
        ctx.fillStyle = style.center_bg;
        fillRound(ctx, box.x, box.y, box.w, box.h, Math.min(box.w, box.h) * 0.22);
        if (style.center_type === 'text') {
            ctx.fillStyle = style.center_color;
            ctx.font = `700 ${box.fs}px ${family}`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(style.center_text, box.x + box.w / 2, box.y + box.h / 2 + box.fs * 0.05);
        } else if (logo) {
            const inner = box.w * 0.84;
            const scale = Math.min(inner / logo.naturalWidth, inner / logo.naturalHeight);
            const lw = logo.naturalWidth * scale;
            const lh = logo.naturalHeight * scale;
            ctx.drawImage(logo, box.x + (box.w - lw) / 2, box.y + (box.h - lh) / 2, lw, lh);
        }
    }

    // Caption band.
    if (style.caption_position !== 'none' && style.caption_text.trim()) {
        const weight = style.caption_bold ? 700 : 500;
        const fs = fitFont(ctx, style.caption_text, style.caption_size * W, W - 2 * (border + pad), weight, family);
        // Centred in the space between the frame edge and the QR's area.
        const y = above ? (border + regionTop) / 2 : (regionTop + regionH + H - border) / 2;
        ctx.fillStyle = style.caption_color;
        ctx.font = `${weight} ${fs}px ${family}`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(style.caption_text, W / 2, y);
    }

    return canvas;
}

/** Loads what a style needs (font, logo) once, then renders blocks quickly. */
export async function prepareRenderer(style, logoUrl) {
    await ensureFonts(style);
    const logo = style.center_type === 'logo' && logoUrl ? await loadImage(logoUrl).catch(() => null) : null;

    return (text, width) => renderQrBlock({ text, style, width, logo });
}

/** A live preview image (data URL) of a style, re-rendered when it changes. */
export function useQrPreview(style, logoUrl, width = 480) {
    const [src, setSrc] = useState(null);
    const key = JSON.stringify(style) + (logoUrl ?? '');

    useEffect(() => {
        let cancelled = false;
        prepareRenderer(style, logoUrl)
            .then((render) => !cancelled && setSrc(render(`${window.location.origin}/qr/SAMPLE`, width).toDataURL('image/png')))
            .catch(() => !cancelled && setSrc(null));

        return () => {
            cancelled = true;
        };
    }, [key, width]);

    return src;
}
