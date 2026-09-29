import { useRef } from 'react';
import { FONT_STACKS, MAX_BOX_RATIO, MIN_BOX_RATIO, formatSerial } from '@/lib/qrStyle';

// A sticker design shown at any size: the background image with the QR
// block on top. Positions are fractions of the image (x/size of its width,
// y of its height), exactly as saved and printed. `qrSrc` is the rendered
// block (lib/qrRender.js), so what you see is what prints.

/** Smallest QR block while editing (fraction of the image width). */
export const MIN_QR_SIZE = 0.05;

/**
 * Keeps the block wholly on the image.
 * aspect = image height / width; block = block height / width (1 = bare square QR).
 */
export function clampQr({ x, y, size }, aspect, block = 1) {
    // The block's height as a fraction of the image height is size * block / aspect.
    const s = Math.min(Math.max(size, MIN_QR_SIZE), 1, aspect / block);
    const maxX = 1 - s;
    const maxY = 1 - (s * block) / aspect;

    return { x: Math.min(Math.max(x, 0), maxX), y: Math.min(Math.max(y, 0), maxY), size: s };
}

/**
 * Keeps a custom box shape (height / width) within limits and on the image.
 * aspect = image height / width.
 */
export function clampRatio(ratio, { y, size }, aspect) {
    const roomBelow = ((1 - y) * aspect) / size;
    return Math.min(Math.max(ratio, MIN_BOX_RATIO), MAX_BOX_RATIO, roomBelow);
}

const handleClass = 'absolute touch-none rounded-full border-2 border-white bg-brand-accent';

/**
 * `editable`: drag the block to move it; drag the corner to resize it, the
 * right edge to change only its width, the bottom edge only its height. Or
 * focus it and use the arrow keys (Shift = bigger steps) and +/- to resize.
 * `onChange(qr, ratio)` - ratio is only passed when the box's shape changed.
 */
export default function QrDesignStage({ imageUrl, aspect, block = 1, qr, qrSrc, serialStyle = null, onChange, editable = false, className = '' }) {
    const stage = useRef(null);
    const drag = useRef(null);

    function start(e, mode) {
        if (!editable) return;
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.setPointerCapture(e.pointerId);
        drag.current = { mode, startX: e.clientX, startY: e.clientY, from: qr, block, rect: stage.current.getBoundingClientRect() };
    }

    function move(e) {
        const d = drag.current;
        if (!d) return;
        // dx is a fraction of the image width; dy of its height (x aspect turns it into image widths).
        const dx = (e.clientX - d.startX) / d.rect.width;
        const dy = (e.clientY - d.startY) / d.rect.height;
        // The box's height, in image widths, while dragging.
        const boxHeight = d.from.size * d.block;

        if (d.mode === 'move') {
            onChange(clampQr({ ...d.from, x: d.from.x + dx, y: d.from.y + dy }, aspect, d.block));
        } else if (d.mode === 'width') {
            // Right edge: wider/narrower, same height - so the shape changes.
            const size = Math.min(Math.max(d.from.size + dx, MIN_QR_SIZE), 1 - d.from.x);
            const next = { ...d.from, size };
            onChange(next, clampRatio(boxHeight / size, next, aspect));
        } else if (d.mode === 'height') {
            // Bottom edge: taller/shorter, same width.
            onChange(d.from, clampRatio((boxHeight + dy * aspect) / d.from.size, d.from, aspect));
        } else {
            // Corner: both, keeping the shape. Top-left stays put.
            onChange(clampQr({ ...d.from, size: d.from.size + Math.max(dx, (dy * aspect) / d.block) }, aspect, d.block));
        }
    }

    function end() {
        drag.current = null;
    }

    function onKeyDown(e) {
        const step = e.shiftKey ? 0.05 : 0.005;
        const moves = {
            ArrowLeft: { x: qr.x - step },
            ArrowRight: { x: qr.x + step },
            ArrowUp: { y: qr.y - step / aspect },
            ArrowDown: { y: qr.y + step / aspect },
            '+': { size: qr.size + 0.01 },
            '=': { size: qr.size + 0.01 },
            '-': { size: qr.size - 0.01 },
        };
        if (!moves[e.key]) return;
        e.preventDefault();
        onChange(clampQr({ ...qr, ...moves[e.key] }, aspect, block));
    }

    return (
        // containerType lets the serial number size itself in cqw (fractions of the artwork's width), like the print.
        <div ref={stage} className={`relative select-none overflow-hidden ${className}`} style={{ aspectRatio: `1 / ${aspect}`, containerType: 'inline-size' }}>
            <img src={imageUrl} alt="" draggable={false} className="absolute inset-0 h-full w-full object-fill" />

            {serialStyle?.serial_enabled && (
                // Bottom centre, baseline serial_offset of the height up - as drawn on the PDF.
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute left-1/2 -translate-x-1/2 whitespace-nowrap leading-none"
                    style={{
                        fontWeight: serialStyle.serial_bold ? 700 : 400,
                        bottom: `${serialStyle.serial_offset * 100}%`,
                        fontSize: `${serialStyle.serial_size * 100}cqw`,
                        color: serialStyle.serial_color,
                        fontFamily: FONT_STACKS[serialStyle.font] ?? FONT_STACKS.sans,
                    }}
                >
                    {formatSerial(1, serialStyle)}
                </span>
            )}

            <div
                role={editable ? 'slider' : undefined}
                tabIndex={editable ? 0 : undefined}
                aria-label={editable ? 'QR position. Drag, or use the arrow keys. Plus and minus change the size.' : undefined}
                aria-valuetext={editable ? `${Math.round(qr.x * 100)}% from left, ${Math.round(qr.y * 100)}% from top, ${Math.round(qr.size * 100)}% wide` : undefined}
                onPointerDown={(e) => start(e, 'move')}
                onPointerMove={move}
                onPointerUp={end}
                onPointerCancel={end}
                onKeyDown={editable ? onKeyDown : undefined}
                className={`absolute outline-none ${editable ? 'cursor-move touch-none outline-2 outline-offset-2 outline-dashed outline-brand-accent focus-visible:outline-solid' : ''}`}
                style={{ left: `${qr.x * 100}%`, top: `${qr.y * 100}%`, width: `${qr.size * 100}%`, aspectRatio: `1 / ${block}` }}
            >
                {qrSrc && <img src={qrSrc} alt="" draggable={false} className="pointer-events-none h-full w-full" />}

                {editable &&
                    [
                        // Right edge: width only.
                        ['width', 'right-0 top-1/2 h-8 w-2.5 -translate-y-1/2 translate-x-1/2 cursor-ew-resize'],
                        // Bottom edge: height only.
                        ['height', 'bottom-0 left-1/2 h-2.5 w-8 -translate-x-1/2 translate-y-1/2 cursor-ns-resize'],
                        // Corner: both, keeping the shape.
                        ['resize', '-bottom-2.5 -right-2.5 h-5 w-5 cursor-nwse-resize'],
                    ].map(([mode, position]) => (
                        <span
                            key={mode}
                            onPointerDown={(e) => start(e, mode)}
                            onPointerMove={move}
                            onPointerUp={end}
                            onPointerCancel={end}
                            aria-hidden="true"
                            className={`${handleClass} ${position}`}
                        />
                    ))}
            </div>
        </div>
    );
}
