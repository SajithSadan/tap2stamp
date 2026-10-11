<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\Shop;
use Illuminate\Http\Request;

/**
 * Does an owner find their QR code and manage to download it? The QR is
 * drawn in the browser, so the page reports each download (or failure)
 * here. Kept on the shop (first opened / first downloaded / counts, for the
 * admin's Shops grid) and in the activity log (each one, for the timeline).
 * An admin viewing as the owner is never counted.
 */
class QrDownloads
{
    public const FORMATS = ['png', 'pdf'];

    public const SOURCES = ['qr_codes', 'settings'];

    /** First visit to the QR codes page only. */
    public function pageViewed(Shop $shop, Request $request): void
    {
        if ($request->attributes->get('viewAsAdmin')) {
            return;
        }

        $first = Shop::whereKey($shop->id)->whereNull('qr_page_viewed_at')->update(['qr_page_viewed_at' => now()]);

        if ($first) {
            ActivityLogger::record('qr.page_opened', 'Opened the QR codes page for the first time', $shop->id);
        }
    }

    /**
     * @param  list<QrCode>  $codes  already checked to be this shop's
     */
    public function record(Shop $shop, Request $request, string $source, string $format, array $codes, bool $ok, ?string $error): void
    {
        if ($request->attributes->get('viewAsAdmin')) {
            return;
        }

        $what = match (true) {
            $source === 'settings' => 'the counter QR from Settings',
            count($codes) === 1 => 'QR code '.$codes[0]->code,
            default => count($codes).' QR codes',
        };
        $as = strtoupper($format);

        if (! $ok) {
            Shop::whereKey($shop->id)->increment('qr_download_failures');
            ActivityLogger::record('qr.download_failed', "Couldn't download {$what} as {$as}".($error ? ": {$error}" : ''), $shop->id, $codes[0] ?? null);

            return;
        }

        Shop::whereKey($shop->id)->increment('qr_downloads');
        Shop::whereKey($shop->id)->whereNull('qr_downloaded_at')->update(['qr_downloaded_at' => now()]);

        $design = $source === 'qr_codes' ? $shop->downloadDesign()?->name : null;
        ActivityLogger::record('qr.downloaded', "Downloaded {$what} as {$as}".($design ? " ({$design} design)" : ''), $shop->id, $codes[0] ?? null);
    }
}
