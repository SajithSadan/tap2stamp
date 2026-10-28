<?php

namespace App\Services;

use App\Models\RewardGame;
use App\Models\Shop;
use App\Support\RewardGames;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Saves a reward game and its prizes in one transaction. Prizes keep their
 * id (and won count) when they come back with it; one left out is deleted.
 * A sent prize id that isn't this game's is refused before anything changes.
 */
class RewardGameBuilder
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>  $prizes
     */
    public function save(Shop $shop, ?RewardGame $game, array $values, array $prizes, ?UploadedFile $image = null, bool $removeImage = false): RewardGame
    {
        $known = $game ? $game->prizes()->pluck('id')->all() : [];
        $sent = collect($prizes)->pluck('id')->filter()->all();
        if (array_diff($sent, $known)) {
            throw ValidationException::withMessages(['prizes' => 'Those prizes have changed - reload the page and try again.']);
        }

        $game = DB::transaction(function () use ($shop, $game, $values, $prizes) {
            $game ??= $shop->rewardGames()->make([
                'status' => RewardGames::DRAFT,
                'position' => (int) $shop->rewardGames()->max('position') + 1,
            ]);
            $game->fill($values)->save();

            $kept = [];
            foreach (array_values($prizes) as $position => $values) {
                $id = $values['id'] ?? null;
                unset($values['id']);
                $prize = $id ? $game->prizes()->findOrFail($id) : $game->prizes()->make();
                $prize->fill([...$values, 'position' => $position])->save();
                $kept[] = $prize->id;
            }
            $game->prizes()->whereNotIn('id', $kept)->delete();

            return $game->load('prizes');
        });

        // The image after the rows are saved; the old file goes once it's replaced or removed.
        if ($image || $removeImage) {
            $old = $game->image_path;
            // Random file name (hashName) - never the owner's original filename.
            // Not fillable: only set here, from a validated upload.
            $game->forceFill(['image_path' => $image?->store("reward-games/{$shop->id}", 'uploads')])->save();
            if ($old) {
                Storage::disk('uploads')->delete($old);
            }
        }

        return $game;
    }

    /** Deletes the game, its prizes and its image file. */
    public function delete(RewardGame $game): void
    {
        $path = $game->image_path;
        $game->delete();
        if ($path) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
