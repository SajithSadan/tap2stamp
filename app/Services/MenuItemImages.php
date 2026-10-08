<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\Shop;
use finfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos for menu items: look the item up in the product catalog, have
 * each candidate checked, and only then store it on the `uploads` disk
 * (public/uploads - no storage:link on the host). Run in small batches
 * driven by the open menu editor, since there are no queue workers on the host.
 *
 * Who checks is an admin setting (Setting::MENU_PHOTO_CHECK): Gemini (`ai`,
 * fetchFor) or the person in the editor (`manual`, forReview → confirm),
 * who is shown each candidate and answers "Is this {item}?". Without a
 * Gemini key it's always manual.
 */
class MenuItemImages
{
    public const BATCH = 3;

    public const MAX_BYTES = 3 * 1024 * 1024;

    public const MODE_AI = 'ai';

    public const MODE_MANUAL = 'manual';

    /** Uploaded photos are stored at most this wide / tall (the editor sends 800 x 800). */
    public const UPLOAD_MAX_SIDE = 1200;

    /** Above this, GD would need too much memory on shared hosting to decode it. */
    public const UPLOAD_MAX_PIXELS = 16_000_000;

    /** How long candidates wait for a yes / no (kept server-side: the catalog's keys never reach the browser). */
    public const REVIEW_MINUTES = 30;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private ProductCatalog $catalog, private MenuImageVerifier $verifier) {}

    /** Offered whenever the catalog is set up; a person can always do the checking. */
    public function enabled(): bool
    {
        return $this->catalog->configured();
    }

    public function aiAvailable(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /** `ai` only when the admin chose it and there's a Gemini key; otherwise `manual`. */
    public function mode(): string
    {
        return Setting::get(Setting::MENU_PHOTO_CHECK, self::MODE_AI) === self::MODE_AI && $this->aiAvailable()
            ? self::MODE_AI
            : self::MODE_MANUAL;
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
        $candidates = $this->search($item, $query);

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

    /**
     * Manual checking, step 1: the item's catalog photos, downloaded and held
     * for REVIEW_MINUTES under random tokens the editor shows them by
     * (reviewImage) and answers with (confirm). Empty = nothing in the
     * catalog, and the item is marked so.
     *
     * @return list<array{token: string, label: ?string}>
     */
    public function forReview(MenuItem $item, ?string $query = null): array
    {
        $review = [];
        foreach ($this->search($item, $query) as $candidate) {
            [$bytes, $mime] = $this->download($candidate) ?? [null, null];
            if ($bytes === null) {
                continue;
            }

            $token = Str::random(40);
            Cache::put(self::reviewKey($token), [
                'shop_id' => $item->section->shop_id,
                'item_id' => $item->id,
                // base64: safe in any cache store.
                'bytes' => base64_encode($bytes),
                'mime' => $mime,
            ], now()->addMinutes(self::REVIEW_MINUTES));
            $review[] = ['token' => $token, 'label' => $candidate['name'] ?? null];
        }

        if ($review === []) {
            $this->missed($item, 'not_found', null, 'Not in the product catalog');
        }

        return $review;
    }

    /** A held candidate's bytes + MIME, for this shop only. */
    public function reviewImage(Shop $shop, string $token): ?array
    {
        $held = Cache::get(self::reviewKey($token));

        return $held && $held['shop_id'] === $shop->id ? [base64_decode($held['bytes']), $held['mime']] : null;
    }

    /**
     * Manual checking, step 2: the person said "yes" to $token (stored as the
     * item's photo), or "no" to every photo ($token null: marked rejected).
     * Null when the token isn't this item's (expired, or someone else's).
     *
     * @return array{status: string, reason: ?string}|null
     */
    public function confirm(MenuItem $item, ?string $token, string $by): ?array
    {
        if ($token === null) {
            return $this->missed($item, 'rejected', null, "None of the catalog photos was right (checked by {$by})");
        }

        $held = Cache::pull(self::reviewKey($token));
        if (! $held || $held['item_id'] !== $item->id) {
            return null;
        }

        $reason = "Confirmed by {$by}";
        $this->store($item, base64_decode($held['bytes']), $held['mime'], ['confidence' => null, 'reason' => $reason]);

        return ['status' => 'found', 'reason' => $reason];
    }

    /**
     * The shop's own photo for an item (usually cropped + compressed in the
     * editor). Re-encoded here as WebP, at most UPLOAD_MAX_SIDE, so whatever
     * arrives is stored small and without its metadata (phone photos carry
     * GPS location in EXIF). Null when it isn't a usable image.
     *
     * @return array{status: string, reason: string}|null
     */
    public function upload(MenuItem $item, string $bytes, string $by): ?array
    {
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] * $size[1] > self::UPLOAD_MAX_PIXELS || ! ($image = @imagecreatefromstring($bytes))) {
            return null;
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::UPLOAD_MAX_SIDE / max($width, $height));
        if ($scale < 1) {
            // imagecopyresampled, not imagescale: the latter fails with some filters on some GD builds.
            $resized = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
            $image = $resized;
        }

        ob_start();
        imagewebp($image, null, 80);
        $webp = ob_get_clean();

        $reason = "Uploaded by {$by}";
        $this->store($item, $webp, 'image/webp', ['confidence' => null, 'reason' => $reason], 'uploaded');

        return ['status' => 'uploaded', 'reason' => $reason];
    }

    private static function reviewKey(string $token): string
    {
        return "menu-photo-review:{$token}";
    }

    /** The item's catalog candidates: as typed (or $query), then the looser search terms. */
    private function search(MenuItem $item, ?string $query): array
    {
        foreach (self::searchTerms($query ?: $item->name) as $term) {
            if ($candidates = $this->candidates($item->name, $this->catalog->search($term))) {
                return $candidates;
            }
        }

        return [];
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

    private function store(MenuItem $item, string $bytes, string $mime, array $verdict, string $status = 'found'): void
    {
        $shopId = $item->section->shop_id;
        $path = "menu-items/{$shopId}/".Str::random(32).'.'.self::TYPES[$mime];
        Storage::disk('uploads')->put($path, $bytes);

        $old = $item->image_path;
        $item->update([
            'image_path' => $path,
            'image_status' => $status,
            'image_confidence' => $verdict['confidence'],
            'image_reason' => $verdict['reason'],
        ]);

        if ($old && $old !== $path) {
            Storage::disk('uploads')->delete($old);
        }
    }
}
