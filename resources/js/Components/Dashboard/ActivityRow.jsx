import { LuGift, LuStamp } from 'react-icons/lu';

export default function ActivityRow({ entry }) {
    const redeemed = entry.action === 'reward_redeemed';
    const Icon = redeemed ? LuGift : LuStamp;

    return (
        <li className="flex items-center gap-3 px-5 py-3">
            <span
                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${
                    redeemed ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-accent/10 text-brand-accent'
                }`}
            >
                <Icon className="h-4 w-4" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-brand-text">{entry.customer_name}</p>
                <p className="truncate text-xs text-brand-muted">
                    {redeemed ? 'Redeemed a reward' : 'Got a stamp'}
                    {entry.staff_name && ` · by ${entry.staff_name}`}
                </p>
            </div>
            <p className="shrink-0 text-xs text-brand-muted">{entry.created_at}</p>
        </li>
    );
}
