<?php

namespace App\Services;

use App\Models\MenuBanner;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Promotion banners: up to MAX images shown as a carousel above a shop's
 * menu. Saved straight away from the menu editor (admin or owner), not part
 * of the menu's Save, and kept when the menu itself is cleared.
 */
class MenuBanners
{
    public const MAX = 5;

    /** Stored at most this wide / tall, as WebP. */
    private const MAX_SIDE = 1600;

    /** Larger images are refused before decoding, so GD can't run out of memory. */
    private const MAX_PIXELS = 16_000_000;

    /** @return list<array{id: int, url: string}> */
    public function forClient(Shop $shop): array
    {
        return $shop->menuBanners()->get()->map(fn (MenuBanner $banner) => $banner->toClient())->all();
    }

    /**
     * Re-encodes the image (smaller file, no EXIF / GPS) and adds it last.
     * Null when it isn't a usable image or the shop already has MAX.
     */
    public function add(Shop $shop, string $bytes): ?MenuBanner
    {
        $webp = $this->encode($bytes);
        if ($webp === null) {
            return null;
        }

        $banner = DB::transaction(function () use ($shop, $webp) {
            Shop::whereKey($shop->id)->lockForUpdate()->first(); // two uploads at once can't make a sixth
            if ($shop->menuBanners()->count() >= self::MAX) {
                return null;
            }

            $path = "menu-banners/{$shop->id}/".Str::random(32).'.webp';
            Storage::disk('uploads')->put($path, $webp);

            return $shop->menuBanners()->create([
                'image_path' => $path,
                'position' => (int) $shop->menuBanners()->max('position') + 1,
            ]);
        });

        if ($banner) {
            ActivityLogger::record('menu.banner_added', 'Added a menu promotion banner', $shop->id);
        }

        return $banner;
    }

    public function remove(Shop $shop, MenuBanner $banner): void
    {
        Storage::disk('uploads')->delete($banner->image_path);
        $banner->delete();

        ActivityLogger::record('menu.banner_removed', 'Removed a menu promotion banner', $shop->id);
    }

    /** $ids = every one of the shop's banners, in the new order (checked by the caller). */
    public function reorder(Shop $shop, array $ids): void
    {
        DB::transaction(function () use ($shop, $ids) {
            foreach (array_values($ids) as $position => $id) {
                $shop->menuBanners()->whereKey($id)->update(['position' => $position + 1]);
            }
        });
    }

    private function encode(string $bytes): ?string
    {
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS || ! ($image = @imagecreatefromstring($bytes))) {
            return null;
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        if ($scale < 1) {
            // imagecopyresampled, not imagescale (fails with some filters on some GD builds) - as for item photos.
            $resized = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
            $image = $resized;
        }

        ob_start();
        imagewebp($image, null, 82);

        return ob_get_clean() ?: null;
    }
}
