<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Shop;
use finfo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos for menu items: look the item up in the product catalog, have
 * Gemini check each candidate really shows it, and only then store it on
 * the `uploads` disk (public/uploads - no storage:link on the host). Run in
 * small batches driven by the open menu editor, since there are no queue
 * workers on the host.
 */
class MenuItemImages
{
    public const BATCH = 3;

    public const MAX_BYTES = 3 * 1024 * 1024;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private ProductCatalog $catalog, private MenuImageVerifier $verifier) {}

    /** Offered only with the catalog keys and a Gemini key. */
    public function enabled(): bool
    {
        return $this->catalog->configured() && filled(config('services.gemini.key'));
    }

    /**
     * The next few items of the shop that were never looked for.
     *
     * @return array{done: int, remaining: int, unavailable: bool, items: list<array<string, mixed>>}
     */
    public function fetchNext(Shop $shop, int $limit = self::BATCH): array
    {
        $items = $this->untried($shop)->with('section:id,name,shop_id')->limit($limit)->get();
        $done = [];
        $unavailable = false;

        foreach ($items as $item) {
            if (! $outcome = $this->fetchFor($item)) {
                $unavailable = true; // Gemini can't check right now - stop, try again later.
                break;
            }
            $done[] = self::summary($item->fresh('section'), $outcome);
        }

        return [
            'done' => count($done),
            'remaining' => $this->untried($shop)->count(),
            'unavailable' => $unavailable,
            'items' => $done,
        ];
    }

    /**
     * Looks for one item's photo. Saves it only if Gemini accepts it. An item
     * that already has a photo keeps it (and its status) when nothing better
     * turns up.
     *
     * @return array{status: string, reason: ?string}|null what happened, or null when Gemini couldn't be used (nothing changed)
     */
    public function fetchFor(MenuItem $item, ?string $query = null): ?array
    {
        $candidates = [];
        foreach (self::searchTerms($query ?: $item->name) as $term) {
            if ($candidates = $this->candidates($item->name, $this->catalog->search($term))) {
                break;
            }
        }

        if ($candidates === []) {
            return $this->missed($item, 'not_found', null, 'Not in the product catalog');
        }

        $closest = null;
        foreach ($candidates as $candidate) {
            [$bytes, $mime] = $this->download($candidate) ?? [null, null];
            if ($bytes === null) {
                continue;
            }

            $verdict = $this->verifier->verify($item->name, $item->section?->name, $item->description, $bytes, $mime, $candidate['name'] ?? null);
            if ($verdict === null) {
                return null;
            }

            Log::info('Menu photo verdict', ['item' => $item->id, 'candidate' => $candidate['name'] ?? null] + $verdict);

            if ($this->verifier->accepted($verdict)) {
                $this->store($item, $bytes, $mime, $verdict);

                return ['status' => 'found', 'reason' => $verdict['reason']];
            }

            if (! $closest || $verdict['confidence'] > $closest['confidence']) {
                $closest = $verdict;
            }
        }

        return $closest
            ? $this->missed($item, 'rejected', $closest['confidence'], mb_substr("Closest: {$closest['detected']} - {$closest['reason']}", 0, 500))
            : $this->missed($item, 'not_found', null, 'No usable image in the catalog');
    }

    /** @return array{status: string, reason: string} */
    private function missed(MenuItem $item, string $status, ?float $confidence, string $reason): array
    {
        if (! $item->image_path) {
            $item->update(['image_status' => $status, 'image_confidence' => $confidence, 'image_reason' => $reason]);
        }

        return ['status' => $status, 'reason' => $reason];
    }

    /**
     * The catalog matches the text as typed ("Coca-Cola 330ml" misses its
     * COCA-COLA-330ML), so a miss is retried hyphenated, then with the main
     * word alone. A looser search is safe: Gemini still has to accept the photo.
     *
     * @return list<string>
     */
    public static function searchTerms(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        // The longest word that isn't a size / number ("330ml", "2x").
        $main = collect(explode(' ', $name))
            ->reject(fn ($word) => preg_match('/\d/', $word) || mb_strlen($word) < 3)
            ->sortByDesc(fn ($word) => mb_strlen($word))
            ->first();

        return array_values(array_unique(array_filter([$name, str_replace(' ', '-', $name), $main])));
    }

    /**
     * What the editor merges back into its rows (matched by section + name),
     * plus this search's outcome for the progress panel.
     *
     * @param  array{status: string, reason: ?string}  $outcome
     */
    public static function summary(MenuItem $item, array $outcome): array
    {
        return [
            'outcome' => $outcome['status'],
            'outcome_reason' => $outcome['reason'],
            'section' => $item->section?->name,
            'name' => $item->name,
            'image_path' => $item->image_path,
            'image_url' => $item->imageUrl(),
            'image_status' => $item->image_status,
            'image_reason' => $item->image_reason,
        ];
    }

    private function untried(Shop $shop)
    {
        return $shop->menuItems()->whereNull('menu_items.image_status')
            ->orderBy('menu_sections.position')->orderBy('menu_items.position')
            ->select('menu_items.*');
    }

    /** Direct images first, then by name similarity, at most max_candidates, no duplicates. */
    private function candidates(string $name, array $found): array
    {
        $target = Str::lower($name);
        $direct = array_values(array_filter($found, fn ($c) => ! empty($c['direct'])));
        $listed = collect($found)->reject(fn ($c) => ! empty($c['direct']))->unique('id')
            ->sortByDesc(function ($c) use ($target) {
                similar_text($target, Str::lower((string) ($c['name'] ?? '')), $percent);

                return $percent;
            })->values()->all();

        return array_slice([...$direct, ...$listed], 0, (int) config('services.product_api.max_candidates', 3));
    }

    /** @return array{0: string, 1: string}|null bytes + MIME sniffed from them (jpeg/png/webp only) */
    private function download(array $candidate): ?array
    {
        $image = $this->catalog->image($candidate);
        if (! $image || $image[0] === '' || strlen($image[0]) > self::MAX_BYTES) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($image[0]);

        return isset(self::TYPES[$mime]) ? [$image[0], $mime] : null;
    }

    private function store(MenuItem $item, string $bytes, string $mime, array $verdict): void
    {
        $shopId = $item->section->shop_id;
        $path = "menu-items/{$shopId}/".Str::random(32).'.'.self::TYPES[$mime];
        Storage::disk('uploads')->put($path, $bytes);

        $old = $item->image_path;
        $item->update([
            'image_path' => $path,
            'image_status' => 'found',
            'image_confidence' => $verdict['confidence'],
            'image_reason' => $verdict['reason'],
        ]);

        if ($old && $old !== $path) {
            Storage::disk('uploads')->delete($old);
        }
    }
}
