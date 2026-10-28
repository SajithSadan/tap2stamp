import { STATUS_STYLES } from '@/lib/rewardGames';

/** Draft / Live / Paused. */
export default function GameStatusChip({ status }) {
    const style = STATUS_STYLES[status] ?? STATUS_STYLES.draft;

    return <span className={`inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold ${style.className}`}>{style.label}</span>;
}
