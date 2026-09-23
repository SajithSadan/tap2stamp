import { LuActivity } from 'react-icons/lu';
import ActivityRow from '@/Components/Dashboard/ActivityRow';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { EmptyState, Pagination, Panel } from '@/Components/Dashboard/Ui';

export default function Activity({ shop, activity }) {
    return (
        <OwnerLayout shop={shop} title="Activity" description="Every stamp and redeemed reward, newest first.">
            <Panel bodyClassName="">
                {activity.data.length === 0 ? (
                    <EmptyState icon={LuActivity} title="No activity yet">
                        Stamps and redeemed rewards show up here once staff start scanning.
                    </EmptyState>
                ) : (
                    <>
                        <ul className="divide-y divide-brand-border">
                            {activity.data.map((entry) => (
                                <ActivityRow key={entry.id} entry={entry} />
                            ))}
                        </ul>
                        <Pagination paginator={activity} only={['activity']} />
                    </>
                )}
            </Panel>
        </OwnerLayout>
    );
}
