import { Link } from '@inertiajs/react';
import { LuActivity, LuChevronRight, LuGift, LuPackage, LuStamp, LuStar, LuUsers } from 'react-icons/lu';
import DailyBarChart from '@/Components/Dashboard/DailyBarChart';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { EmptyState, Panel, StatTile } from '@/Components/Dashboard/Ui';
import ActivityRow from '@/Components/Dashboard/ActivityRow';
import OrderOffer from '@/Components/Dashboard/OrderOffer';

/** After ordering: one slim line about the order still on its way, until it's delivered. */
function ActiveOrder({ order }) {
    const step = order.steps.find((s) => s.key === order.stage);

    return (
        <Link
            href="/dashboard/orders"
            className="mb-4 flex items-center gap-3 rounded-2xl border border-brand-border bg-brand-card px-4 py-3 shadow-sm transition-colors hover:bg-brand-bg"
        >
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                <LuPackage className="h-4 w-4" />
            </span>
            <span className="min-w-0 flex-1 text-sm">
                <span className="block truncate font-semibold text-brand-text">
                    Your {order.quantity > 1 ? `${order.quantity} × ` : ''}
                    {order.product_name}: {order.awaiting_payment ? 'awaiting payment' : step?.label}
                </span>
                <span className="block truncate text-xs text-brand-muted">
                    {order.awaiting_payment
                        ? `Reference ${order.reference} · See how to pay`
                        : `${step?.date}${order.tracking_url ? ' · Track your parcel' : ' · See progress'}`}
                </span>
            </span>
            <LuChevronRight className="h-4 w-4 shrink-0 text-brand-muted" />
        </Link>
    );
}

export default function Overview({ shop, orderOffer, activeOrder, stats, chart, recentActivity }) {
    const totalStamps = chart.reduce((sum, d) => sum + d.stamps, 0);
    const totalRedeemed = chart.reduce((sum, d) => sum + d.redeemed, 0);

    return (
        <OwnerLayout shop={shop} title="Overview" description="How your loyalty card is doing.">
            {orderOffer && <OrderOffer offer={orderOffer} />}
            {!orderOffer && activeOrder && <ActiveOrder order={activeOrder} />}

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
