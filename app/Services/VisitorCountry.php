<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The visitor's country (ISO alpha-2) from their IP, for the landing page's
 * currency. Cloudflare's CF-IPCountry header first (free, if the site is
 * behind Cloudflare), else ipinfo.io Lite (IPINFO_TOKEN, free, country
 * only), cached per IP for a week. Null when unknown - local / private IPs,
 * no token, or the lookup failed - and the page then uses its default
 * currency. Never blocks the page for long (2 s timeout).
 */
class VisitorCountry
{
    private const CACHE_DAYS = 7;

    public function for(Request $request): ?string
    {
        $header = strtoupper((string) $request->header('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $header) && ! in_array($header, ['XX', 'T1'], true)) {
            return $header;
        }

        $ip = (string) $request->ip();
        $token = config('services.ipinfo.token');

        if (! $token || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        // Cached as '' for "looked up, unknown", so a failing IP isn't retried on every visit.
        $country = Cache::remember('visitor-country:'.hash('sha256', $ip), now()->addDays(self::CACHE_DAYS), function () use ($ip, $token) {
            try {
                $response = Http::timeout(2)->acceptJson()->withToken($token)->get('https://api.ipinfo.io/lite/'.$ip);
            } catch (ConnectionException $e) {
                Log::warning('Visitor country lookup unreachable', ['message' => $e->getMessage()]);

                return '';
            }

            $code = strtoupper((string) $response->json('country_code'));

            return $response->successful() && preg_match('/^[A-Z]{2}$/', $code) ? $code : '';
        });

        return $country !== '' ? $country : null;
    }
}
