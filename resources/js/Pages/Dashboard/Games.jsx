import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { LuGamepad2, LuPause, LuPencil, LuPlay, LuPlus, LuTrash2 } from 'react-icons/lu';
import { useConfirm } from '@/Components/ConfirmDialog';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { EmptyState, Panel, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import GameStatusChip from '@/Components/Games/GameStatusChip';
import { GAME_TYPE_ICONS, oddsLabel } from '@/lib/rewardGames';

/** Rewards & games - the owner's games, each a draft, live or paused. */
export default function Games({ shop, games, options }) {
    const [busy, setBusy] = useState(null);
    const [confirm, confirmDialog] = useConfirm();
    const typeLabel = Object.fromEntries(options.types.map((t) => [t.key, t.label]));
    const opts = (id) => ({ preserveScroll: true, onStart: () => setBusy(id), onFinish: () => setBusy(null) });

    const setStatus = (game, status) => router.put(`/dashboard/games/${game.id}/status`, { status }, opts(game.id));

    async function remove(game) {
        const ok = await confirm({
            title: `Delete ${game.name}?`,
            message: 'The game and its prizes are removed. This can\'t be undone.',
            confirmLabel: 'Delete game',
            danger: true,
        });
        if (ok) router.delete(`/dashboard/games/${game.id}`, opts(game.id));
    }

    const newGame = (
        <Link href="/dashboard/games/create" className={primaryButton}>
            <LuPlus className="h-4 w-4" /> New game
        </Link>
    );

    return (
        <OwnerLayout shop={shop} title="Reward games" description="Games your customers play for prizes when they collect stamps." actions={games.length > 0 && newGame}>
            {games.length === 0 ? (
                <Panel>
                    <EmptyState icon={LuGamepad2} title="No games yet">
                        Create a prize wheel, scratch card, slot machine or another game with your own prizes.
                    </EmptyState>
                    <div className="-mt-4 pb-8 text-center">{newGame}</div>
                </Panel>
            ) : (
                <Panel bodyClassName="divide-y divide-brand-border">
                    {games.map((game) => {
                        const Icon = GAME_TYPE_ICONS[game.type] ?? LuGamepad2;

                        return (
                            <div key={game.id} className={`flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 ${busy === game.id ? 'opacity-60' : ''}`}>
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                                    <Icon className="h-5 w-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={`/dashboard/games/${game.id}/edit`} className="truncate font-semibold text-brand-text hover:underline">
                                            {game.name}
                                        </Link>
                                        <GameStatusChip status={game.status} />
                                    </div>
                                    <p className="mt-0.5 text-sm text-brand-muted">
                                        {typeLabel[game.type]} · {game.trigger_label} · {game.prizes.length} prizes · {oddsLabel(game.prizes)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    {game.status === 'live' ? (
                                        <button type="button" onClick={() => setStatus(game, 'paused')} disabled={busy !== null} className={secondaryButton}>
                                            <LuPause className="h-4 w-4" /> Pause
                                        </button>
                                    ) : (
                                        <button type="button" onClick={() => setStatus(game, 'live')} disabled={busy !== null} className={primaryButton}>
                                            <LuPlay className="h-4 w-4" /> Publish
                                        </button>
                                    )}
                                    <Link href={`/dashboard/games/${game.id}/edit`} className={`${secondaryButton} !px-2.5`} aria-label={`Edit ${game.name}`}>
                                        <LuPencil className="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => remove(game)}
                                        disabled={busy !== null}
                                        className={`${secondaryButton} !px-2.5 hover:!text-red-600`}
                                        aria-label={`Delete ${game.name}`}
                                    >
                                        <LuTrash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </Panel>
            )}
            {confirmDialog}
        </OwnerLayout>
    );
}
