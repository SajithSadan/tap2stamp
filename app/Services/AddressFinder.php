<?php

namespace App\Services;

use App\Support\ShopContact;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UK address lookup by postcode + house number/name (findaddress.io).
 * Server-side only - the API key is sent from here, never from the browser.
 * Found addresses are cached, so the same lookup never costs a second credit.
 */
class AddressFinder
{
    private const URL = 'https://findaddress.io/API/';

    private const CACHE_DAYS = 30;

    public function configured(): bool
    {
        return filled(config('services.findaddress.key'));
    }

    /**
     * @return array{status: 'found'|'partial'|'not_found'|'unavailable', address?: array<string, string>}
     */
    public function find(string $postcode, string $house): array
    {
        $postcode = ShopContact::postcode($postcode);
        $house = trim($house);
        $cacheKey = 'findaddress:'.sha1(strtolower("{$postcode}|{$house}"));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(8)
                ->withHeaders(['x-api-key' => (string) config('services.findaddress.key')])
                ->post(self::URL, ['postcode' => str_replace(' ', '', $postcode), 'house' => $house]);
        } catch (ConnectionException $e) {
            Log::warning('Address lookup unreachable', ['message' => $e->getMessage()]);

            return ['status' => 'unavailable'];
        }

        $body = $response->json() ?? [];
        $result = $body['result'] ?? null;

        if ($result === 'Success' || $result === 'Partial match') {
            $found = [
                'status' => $result === 'Success' ? 'found' : 'partial',
                'address' => $this->toShopAddress($body['expandedAddress'] ?? [], $house, $postcode),
            ];

            Cache::put($cacheKey, $found, now()->addDays(self::CACHE_DAYS));

            return $found;
        }

        // A bad postcode is the user's to fix; anything else (key, credits,
        // their server) means "type it in yourself" and a note in the log.
        $status = (int) ($body['statusCode'] ?? $response->status());

        if ($status === 400 || $status === 404) {
            return ['status' => 'not_found'];
        }

        Log::warning('Address lookup failed', ['status' => $status, 'error' => $body['errorMsg'] ?? null]);

        return ['status' => 'unavailable'];
    }

    /**
     * findaddress.io's fields → our address_line1 / address_line2 / town /
     * postcode. "10" + "Downing street" → "10 Downing Street"; a house name
     * stays on its own line: "Rose Cottage, Mill Lane".
     *
     * @return array<string, string>
     */
    private function toShopAddress(array $found, string $house, string $postcode): array
    {
        $tidy = fn (?string $value) => ucwords(trim((string) $value));
        $number = $tidy($found['house'] ?? '') ?: $house;
        $street = $tidy($found['street'] ?? '');
        $line1 = match (true) {
            $street === '' => $number,
            (bool) preg_match('/^\d/', $number) => "{$number} {$street}",
            default => "{$number}, {$street}",
        };
        $pCode = ShopContact::postcode((string) ($found['pCode'] ?? ''));

        return [
            'address_line1' => $line1,
            'address_line2' => $tidy($found['locality'] ?? ''),
            'town' => $tidy($found['town'] ?? ''),
            // A partial match may only know the area ("SW1A") - keep the full one typed.
            'postcode' => strlen($pCode) >= strlen($postcode) ? $pCode : $postcode,
        ];
    }
}
