<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopCodeAttempt;
use App\Models\ShopCodeLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A staff phone that isn't set up joins its shop by typing the shop code
 * (shops.shop_code): the right code adds the device straight away, the same
 * as the owner's setup QR, and returns its token.
 *
 * Wrong codes are counted per day: 3 from one browser (a random id it keeps
 * in localStorage) lock that browser, and 10 from one network (IP) lock the
 * network, as a backstop for a browser that clears its id. Locks never
 * expire: only an admin unlocks them (Admin → Settings → Shop codes). After
 * an unlock the count starts again.
 *
 * Returns [httpStatus, body] like StampService.
 */
class ShopCodeJoin
{
    public const MAX_WRONG_PER_BROWSER = 3;

    public const MAX_WRONG_PER_IP = 10;

    /** @return array{0: int, 1: array<string, mixed>} */
    public function attempt(string $code, string $browserId, string $ip, ?string $userAgent): array
    {
        if ($this->activeLock($browserId, $ip)) {
            return $this->locked($browserId);
        }

        $shop = Shop::where('shop_code', self::normalise($code))->first();

        if (! $shop) {
            ShopCodeAttempt::create(['browser_id' => $browserId, 'ip' => $ip, 'success' => false]);

            $wrong = $this->wrongToday('browser_id', $browserId);
            if ($wrong >= self::MAX_WRONG_PER_BROWSER) {
                $this->lock(['browser_id' => $browserId], $userAgent);
            }
            if ($this->wrongToday('ip', $ip) >= self::MAX_WRONG_PER_IP) {
                $this->lock(['ip' => $ip], $userAgent);
            }

            if ($this->activeLock($browserId, $ip)) {
                return $this->locked($browserId);
            }

            $left = self::MAX_WRONG_PER_BROWSER - $wrong;

            return [422, [
                'code' => 'wrong_code',
                'message' => "That code isn't right. {$left} ".($left === 1 ? 'try' : 'tries').' left today.',
                'tries_left' => $left,
            ]];
        }

        ShopCodeAttempt::create(['browser_id' => $browserId, 'ip' => $ip, 'success' => true]);

        // Same answer as the staff app gets for a blocked shop.
        if ($shop->isBlocked()) {
            return [403, ['code' => 'shop_blocked', 'message' => 'This shop is paused.']];
        }

        // The plain token is only ever in this response; only its hash is stored.
        $token = Str::random(64);
        $shop->staffDevices()->create([
            'name' => self::deviceName($userAgent),
            'token_hash' => hash('sha256', $token),
        ]);

        return [200, ['token' => $token, 'shop_name' => $shop->name]];
    }

    /** "k7p 2qx" / "k7p-2qx" → "K7P2QX". */
    public static function normalise(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** What the owner sees in their device list: "Safari on iPhone (shop code)". */
    public static function deviceName(?string $userAgent): string
    {
        return self::describe($userAgent).' (shop code)';
    }

    /**
     * "Chrome on Windows", "Safari on iPhone" - from what the browser says it
     * is, so a desktop browser pretending to be a phone (DevTools device mode)
     * shows as that phone.
     */
    public static function describe(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        $system = match (true) {
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'CrOS') => 'Chromebook',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };
        $browser = match (true) {
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'EdgA/') || str_contains($ua, 'Edg/') || str_contains($ua, 'EdgiOS') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'FxiOS') || str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        return match (true) {
            $browser && $system => "{$browser} on {$system}",
            (bool) $system => $system,
            (bool) $browser => $browser,
            default => 'Unknown device',
        };
    }

    public function activeLock(string $browserId, string $ip): ?ShopCodeLock
    {
        return ShopCodeLock::active()
            ->where(fn ($q) => $q->where('browser_id', $browserId)->orWhere('ip', $ip))
            ->first();
    }

    public function unlock(ShopCodeLock $lock, int $adminId): void
    {
        $lock->update(['unlocked_at' => now(), 'unlocked_by' => $adminId]);
    }

    /** Wrong codes today from this browser / network, since its last unlock. */
    private function wrongToday(string $column, string $value): int
    {
        $lastUnlock = ShopCodeLock::where($column, $value)->max('unlocked_at');
        $since = now()->startOfDay();
        if ($lastUnlock && Carbon::parse($lastUnlock)->gt($since)) {
            $since = Carbon::parse($lastUnlock);
        }

        return ShopCodeAttempt::where($column, $value)
            ->where('success', false)
            ->where('created_at', '>', $since)
            ->count();
    }

    /** @param  array{browser_id?: string, ip?: string}  $who */
    private function lock(array $who, ?string $userAgent): void
    {
        DB::transaction(function () use ($who, $userAgent) {
            $exists = ShopCodeLock::active()->where($who)->lockForUpdate()->exists();
            if (! $exists) {
                ShopCodeLock::create([...$who, 'user_agent' => $userAgent ? mb_strimwidth($userAgent, 0, 255) : null]);
            }
        });
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function locked(string $browserId): array
    {
        return [423, [
            'code' => 'join_locked',
            'message' => 'Too many wrong codes.',
            'device_id' => ShopCodeLock::shortId($browserId),
        ]];
    }
}
