import { motion } from 'framer-motion';
import { PiGiftFill } from 'react-icons/pi';
import { StampIcon, stampColumns, stampTilt } from '@/lib/stampIcons';

// The stamps on a loyalty card, drawn like a real stamp card. Every slot is a
// stamp to collect; the last one shows a gift (the stamp that fills the card):
//  - filled stamps look pressed in ink: accent gradient, soft inner ring,
//    each icon tilted a little differently (the same on every visit);
//  - empty ones are dashed slots with a faint ghost of the icon.
// Rows of 6 (8 -> 6 + 2); a short last row starts from the left.
// Colours come from the shop theme (brand-* variables), so it follows every theme.

const GAP_REM = { sm: 0.375, md: 0.625 };
// Largest a stamp gets (it only shrinks below this on narrow screens): 48px on the card, 24px in previews.
const MAX_REM = { sm: 1.5, md: 3 };

// Pressed-ink look for a filled stamp, mixed from the theme's accent.
const INK = {
    background:
        'radial-gradient(circle at 32% 28%, color-mix(in oklab, var(--color-brand-accent) 70%, white) 0%, var(--color-brand-accent) 55%, color-mix(in oklab, var(--color-brand-accent) 82%, black) 100%)',
    boxShadow: 'inset 0 0 0 2px color-mix(in oklab, white 30%, transparent), inset 0 -2px 3px rgb(0 0 0 / 0.12)',
    color: 'var(--color-brand-accent-text)',
};

const ICON = 'h-[48%] w-[48%]';

/**
 * `stamps` of `total` filled; `previous` = the count before the latest stamp,
 * so a new one pops in. `size` 'sm' for small previews; `animate` off for lists.
 * A real grid: the block is centred (or left-aligned with align="start") and
 * every row starts from the left.
 */
export default function StampGrid({ total, stamps, icon, previous = stamps, size = 'md', animate = true, align = 'center', className = '' }) {
    const cols = stampColumns(total);
    const gap = GAP_REM[size] ?? GAP_REM.md;
    const max = MAX_REM[size] ?? MAX_REM.md;

    return (
        <div
            role="img"
            aria-label={`${stamps} of ${total} stamps`}
            className={`grid ${align === 'start' ? 'justify-start' : 'justify-center'} ${className}`}
            // Each column up to `max` wide, shrinking together on narrow screens.
            style={{ gap: `${gap}rem`, gridTemplateColumns: `repeat(${cols}, minmax(0, ${max}rem))` }}
        >
            {Array.from({ length: total }, (_, i) => i + 1).map((i) => {
                const filled = i <= stamps;
                // The last slot shows a gift: the stamp that completes the card.
                const last = i === total;
                const emptyClass = last ? 'border-2 border-brand-accent/40 bg-brand-accent/10' : 'border-2 border-dashed border-brand-border';

                return (
                    <motion.div
                        key={i}
                        initial={animate ? { scale: 0, opacity: 0 } : false}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ delay: i * 0.03, type: 'spring', stiffness: 400, damping: 20 }}
                        className={`flex aspect-square items-center justify-center rounded-full ${filled ? '' : emptyClass}`}
                        style={filled ? INK : undefined}
                    >
                        {filled ? (
                            <motion.span
                                className="flex h-full w-full items-center justify-center"
                                style={{ rotate: stampTilt(i) }}
                                initial={false}
                                animate={i > previous ? { scale: [0.2, 1.35, 1] } : { scale: 1 }}
                                transition={{ duration: 0.5 }}
                            >
                                {last ? <PiGiftFill className={ICON} /> : <StampIcon icon={icon} className={ICON} />}
                            </motion.span>
                        ) : last ? (
                            <PiGiftFill className={`${ICON} text-brand-accent`} />
                        ) : (
                            // A faint ghost of the icon: the slot waiting to be stamped.
                            <StampIcon icon={icon} className={`${ICON} text-brand-muted opacity-20`} />
                        )}
                    </motion.div>
                );
            })}
        </div>
    );
}
