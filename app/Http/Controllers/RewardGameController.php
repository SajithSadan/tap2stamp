<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRewardGameRequest;
use App\Models\RewardGame;
use App\Services\RewardGameBuilder;
use App\Support\RewardGames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's reward games (Features::GAMES): list, builder, publish /
 * pause, delete. Always the signed-in owner's shop - a game is found
 * through it, so another shop's id is a 404.
 */
class RewardGameController extends Controller
{
    public function index(Request $request): Response
    {
        $shop = $request->user()->shop;

        return Inertia::render('Dashboard/Games', [
            'shop' => DashboardController::shopSummary($shop),
            'games' => $shop->rewardGames()->with('prizes')->get()->map->toBuilder()->values(),
            'options' => RewardGames::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->editor($request, null);
    }

    public function store(SaveRewardGameRequest $request, RewardGameBuilder $builder): RedirectResponse
    {
        $game = $builder->save($request->user()->shop, null, $request->game(), $request->prizes(), $request->file('image'));

        return redirect()->route('dashboard.games.edit', $game->id)->with('status', 'Game saved as a draft. Publish it when you\'re ready.');
    }

    public function edit(Request $request, int $game): Response
    {
        return $this->editor($request, $this->find($request, $game));
    }

    public function update(SaveRewardGameRequest $request, RewardGameBuilder $builder, int $game): RedirectResponse
    {
        $builder->save(
            $request->user()->shop, $this->find($request, $game), $request->game(), $request->prizes(),
            $request->file('image'), $request->boolean('remove_image'),
        );

        return back()->with('status', 'Game saved.');
    }

    /** Publish (live), pause, or back to draft. */
    public function status(Request $request, int $game): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(RewardGames::STATUSES)]])['status'];
        $this->find($request, $game)->update(['status' => $status]);

        return back()->with('status', match ($status) {
            RewardGames::LIVE => 'Game published - customers play it from their next stamp.',
            RewardGames::PAUSED => 'Game paused.',
            default => 'Game moved back to drafts.',
        });
    }

    public function destroy(Request $request, RewardGameBuilder $builder, int $game): RedirectResponse
    {
        $builder->delete($this->find($request, $game));

        return redirect()->route('dashboard.games')->with('status', 'Game deleted.');
    }

    private function editor(Request $request, ?RewardGame $game): Response
    {
        return Inertia::render('Dashboard/GameEditor', [
            'shop' => DashboardController::shopSummary($request->user()->shop),
            'game' => $game?->load('prizes')->toBuilder(),
            'options' => RewardGames::options(),
        ]);
    }

    private function find(Request $request, int $id): RewardGame
    {
        return $request->user()->shop->rewardGames()->findOrFail($id);
    }
}
