import { LuBell, LuCherry, LuClover, LuCrown, LuGem, LuGift, LuStar } from 'react-icons/lu';
import { NO_WIN } from '@/lib/rewardGames';

/*
 * What the customer will see, drawn from the owner's prizes - the builder's
 * live preview (and, later, the real game on the card page). One component
 * per game type; keys match App\Support\RewardGames::TYPES.
 */

const short = (text, max = 12) => {
    const t = (text || '').trim() || 'Prize';
    return t.length > max ? `${t.slice(0, max - 1)}…` : t;
};

/** Prize wheel: equal slices (the chance is in the weights, not the slice size). */
function Wheel({ prizes }) {
    const list = prizes.length ? prizes : [{ title: 'Prize' }, { title: 'Prize' }];
    const n = list.length;
    const r = 110;
    const c = 120;
    const point = (angle, radius = r) => [c + radius * Math.cos(angle), c + radius * Math.sin(angle)];

    return (
        <svg viewBox="0 0 240 252" className="mx-auto w-full max-w-[260px]" role="img" aria-label="Prize wheel preview">
            <g transform="translate(0 12)">
                <circle cx={c} cy={c} r={r + 6} fill="var(--color-brand-deep)" />
                {list.map((prize, i) => {
                    const a0 = (i / n) * 2 * Math.PI - Math.PI / 2;
                    const a1 = ((i + 1) / n) * 2 * Math.PI - Math.PI / 2;
                    const [x0, y0] = point(a0);
                    const [x1, y1] = point(a1);
                    const mid = ((a0 + a1) / 2) * (180 / Math.PI);
                    const noWin = prize.kind === NO_WIN;
                    const dark = !noWin && i % 2 === 0;

                    return (
                        <g key={i}>
                            <path
                                d={n === 1 ? '' : `M${c},${c} L${x0},${y0} A${r},${r} 0 ${a1 - a0 > Math.PI ? 1 : 0} 1 ${x1},${y1} Z`}
                                fill={noWin ? 'var(--color-brand-bg)' : dark ? 'var(--color-brand-accent)' : 'var(--color-brand-card)'}
                                stroke="var(--color-brand-deep)"
                                strokeWidth="1.5"
                            />
                            <text
                                transform={`rotate(${mid} ${c} ${c}) translate(${c + r * 0.6} ${c})`}
                                textAnchor="middle"
                                dominantBaseline="middle"
                                fontSize={n > 6 ? 9 : 11}
                                fontWeight="600"
                                fill={dark ? 'var(--color-brand-accent-text)' : 'var(--color-brand-text)'}
                            >
                                {short(prize.title, n > 6 ? 10 : 12)}
                            </text>
                        </g>
                    );
                })}
                <circle cx={c} cy={c} r="16" fill="var(--color-brand-deep)" stroke="var(--color-brand-card)" strokeWidth="3" />
            </g>
            {/* Pointer at the top. */}
            <path d="M120 26 L110 6 L130 6 Z" fill="var(--color-brand-accent)" stroke="var(--color-brand-deep)" strokeWidth="2" strokeLinejoin="round" />
        </svg>
    );
}

/** Scratch card: the layer the customer scratches off - the owner's image, else plain silver. */
function Scratch({ image }) {
    return (
        <div className="mx-auto w-full max-w-[260px] rounded-2xl bg-brand-deep p-3">
            <div
                className="relative flex aspect-[3/2] items-center justify-center overflow-hidden rounded-xl text-sm font-semibold text-neutral-600"
                style={image ? undefined : { background: 'repeating-linear-gradient(135deg, #d4d4d8 0 10px, #c4c4c8 10px 20px)' }}
            >
                {image && <img src={image} alt="" className="absolute inset-0 h-full w-full object-cover" />}
                <span className={image ? 'relative rounded-full bg-black/60 px-3 py-1 text-xs text-white' : ''}>Scratch here</span>
            </div>
            <p className="mt-2.5 text-center text-xs font-medium text-white/80">Scratch to reveal your prize</p>
        </div>
    );
}

/** Mystery boxes: pick one of three. */
function Boxes() {
    return (
        <div className="mx-auto w-full max-w-[260px]">
            <div className="grid grid-cols-3 gap-2">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="flex aspect-square items-center justify-center rounded-2xl bg-brand-accent text-brand-accent-text">
                        <LuGift className="h-9 w-9" />
                    </div>
                ))}
            </div>
            <p className="mt-2.5 text-center text-xs font-medium text-brand-muted">Pick a box to see your prize</p>
        </div>
    );
}

/** Each winnable prize's symbol on the slot machine, in prize order. */
const SLOT_SYMBOLS = [LuCherry, LuStar, LuGem, LuBell, LuClover, LuCrown];

/** Slot machine: three reels; three of a prize's symbol in the middle row wins it. */
function Slots({ prizes }) {
    const wins = prizes.filter((p) => p.kind !== NO_WIN).slice(0, SLOT_SYMBOLS.length);
    const symbols = SLOT_SYMBOLS.slice(0, Math.max(3, wins.length));

    return (
        <div className="mx-auto w-full max-w-[260px]">
            <div className="rounded-2xl bg-brand-deep p-3">
                <div className="grid grid-cols-3 gap-2">
                    {[0, 1, 2].map((reel) => (
                        <div key={reel} className="flex flex-col items-center gap-1.5 rounded-xl bg-brand-card py-2.5">
                            {[-1, 0, 1].map((row) => {
                                const Icon = symbols[(reel * 2 + row + symbols.length) % symbols.length];

                                return <Icon key={row} className={row === 0 ? 'h-8 w-8 text-brand-accent' : 'h-5 w-5 text-brand-muted/40'} />;
                            })}
                        </div>
                    ))}
                </div>
                <p className="mt-2.5 text-center text-xs font-medium text-white/80">Pull to spin</p>
            </div>
            {wins.length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {wins.map((prize, i) => {
                        const Icon = SLOT_SYMBOLS[i];

                        return (
                            <li key={i} className="flex items-center gap-2 text-xs text-brand-text">
                                <span className="flex shrink-0 text-brand-accent">
                                    <Icon className="h-3.5 w-3.5" />
                                    <Icon className="h-3.5 w-3.5" />
                                    <Icon className="h-3.5 w-3.5" />
                                </span>
                                <span className="truncate">{(prize.title || '').trim() || 'Prize'}</span>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

/** Gift unwrap: one present to tap open. */
function Gift() {
    return (
        <div className="mx-auto w-full max-w-[260px]">
            <div className="mx-auto flex aspect-square w-40 items-center justify-center rounded-3xl bg-brand-accent text-brand-accent-text">
                <LuGift className="h-20 w-20" />
            </div>
            <p className="mt-2.5 text-center text-xs font-medium text-brand-muted">Tap the present to open it</p>
        </div>
    );
}

/** Ball drop: a peg board with one slot per prize at the bottom (equal slots, like the wheel). */
function Plinko({ prizes }) {
    const list = prizes.length ? prizes : [{ title: 'Prize' }, { title: 'Prize' }];
    const n = list.length;
    const slotW = 220 / n;
    const pegs = [];
    for (let row = 0; row < 6; row++) {
        const odd = row % 2 === 1;
        for (let k = 0; k < (odd ? 7 : 8); k++) pegs.push([(odd ? 30 : 15) + k * 30, 42 + row * 22]);
    }

    return (
        <svg viewBox="0 0 240 236" className="mx-auto w-full max-w-[260px]" role="img" aria-label="Ball drop preview">
            <rect width="240" height="236" rx="16" fill="var(--color-brand-deep)" />
            {pegs.map(([x, y], i) => (
                <circle key={i} cx={x} cy={y} r="3" fill="var(--color-brand-card)" opacity="0.7" />
            ))}
            <circle cx="120" cy="18" r="8" fill="var(--color-brand-accent)" />
            {list.map((prize, i) => {
                const noWin = prize.kind === NO_WIN;

                return (
                    <g key={i}>
                        <rect
                            x={10 + i * slotW + 1.5}
                            y="172"
                            width={slotW - 3}
                            height="52"
                            rx="6"
                            fill={noWin ? 'var(--color-brand-bg)' : 'var(--color-brand-card)'}
                            opacity={noWin ? 0.55 : 1}
                        />
                        <text
                            x={10 + (i + 0.5) * slotW}
                            y="198"
                            textAnchor="middle"
                            dominantBaseline="middle"
                            fontSize={n > 5 ? 8 : 10}
                            fontWeight="600"
                            fill="var(--color-brand-text)"
                        >
                            {short(prize.title, n > 5 ? 6 : 9)}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
}

const GAMES = { wheel: Wheel, scratch: Scratch, boxes: Boxes, slots: Slots, gift: Gift, plinko: Plinko };

export default function GamePreview({ type, prizes = [], image = null, headline = null }) {
    const Game = GAMES[type] ?? Wheel;

    return (
        <div>
            {headline && <p className="mb-3 text-center font-heading text-lg font-bold text-brand-text">{headline}</p>}
            <Game prizes={prizes} image={image} />
        </div>
    );
}
