import { Link } from '@inertiajs/react';
import { LuActivity, LuGift, LuStamp, LuStar, LuUsers } from 'react-icons/lu';
import DailyBarChart from '@/Components/Dashboard/DailyBarChart';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { EmptyState, Panel, StatTile } from '@/Components/Dashboard/Ui';
import ActivityRow from '@/Components/Dashboard/ActivityRow';

export default function Overview({ shop, stats, chart, recentActivity }) {
    const totalStamps = chart.reduce((sum, d) => sum + d.stamps, 0);
    const totalRedeemed = chart.reduce((sum, d) => sum + d.redeemed, 0);

    return (
        <OwnerLayout shop={shop} title="Overview" description="How your loyalty card is doing.">
            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatTile
                    icon={LuUsers}
                    label="Customers"
                    value={stats.customer_count}
                    hint={stats.new_customers_7d > 0 ? `+${stats.new_customers_7d} this week` : 'No new sign-ups this week'}
                />
                <StatTile icon={LuStamp} label="Stamps today" value={stats.stamps_today} hint={`${totalStamps} in the last 14 days`} />
                <StatTile
                    icon={LuGift}
                    label="Rewards redeemed"
                    value={stats.rewards_redeemed}
                    hint={stats.rewards_ready > 0 ? `${stats.rewards_ready} ready to claim` : 'None waiting to be claimed'}
                />
                <StatTile
                    icon={LuStar}
                    label="Average rating"
                    value={stats.review_count > 0 ? stats.average_rating.toFixed(1) : '–'}
                    hint={stats.review_count > 0 ? `From ${stats.review_count} ${stats.review_count === 1 ? 'review' : 'reviews'}` : 'No reviews yet'}
                />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-5">
                <Panel
                    className="lg:col-span-3"
                    title="Stamps per day"
                    description={`Last 14 days · ${totalStamps} stamps, ${totalRedeemed} rewards redeemed`}
                >
                    <DailyBarChart days={chart} />
                </Panel>

                <Panel
                    className="lg:col-span-2"
                    title="Recent activity"
                    bodyClassName=""
                    action={
                        <Link href="/dashboard/activity" className="text-sm font-semibold text-brand-accent">
                            View all
                        </Link>
                    }
                >
                    {recentActivity.length === 0 ? (
                        <EmptyState icon={LuActivity} title="No activity yet">
                            Stamps and redeemed rewards show up here once staff start scanning.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-brand-border">
                            {recentActivity.map((entry) => (
                                <ActivityRow key={entry.id} entry={entry} />
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </OwnerLayout>
    );
}
