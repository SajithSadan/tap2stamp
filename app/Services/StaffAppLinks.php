<?php

namespace App\Services;

use App\Models\StaffDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Putting the staff app on an iPhone's home screen. A home-screen app gets
 * its own, empty storage, so it wouldn't have the device's sign-in. The page
 * the staff member adds carries a one-time code instead
 * (/staff?link={code}), never the device token itself. The installed app
 * swaps the code for the token on its first launch. Codes last LIFETIME
 * hours, work once, and only while the device is still approved. The token
 * is held encrypted in the cache until then (only its hash is ever stored
 * in the database).
 */
class StaffAppLinks
{
    public const LIFETIME_HOURS = 24;

    public const CODE_PATTERN = '[A-Za-z0-9]{40}';

    public function issue(StaffDevice $device, string $token): string
    {
        $code = Str::random(40);

        Cache::put(self::key($code), [
            'device_id' => $device->id,
            'token' => Crypt::encryptString($token),
        ], now()->addHours(self::LIFETIME_HOURS));

        return $code;
    }

    /** The device token for a valid, unused code (which is then used up), else null. */
    public function redeem(string $code): ?string
    {
        if (! preg_match('/^'.self::CODE_PATTERN.'$/', $code)) {
            return null;
        }

        $entry = Cache::pull(self::key($code));
        if (! is_array($entry)) {
            return null;
        }

        $token = Crypt::decryptString($entry['token']);

        // Revoked (or re-keyed) since the code was made: nothing to hand out.
        $stillValid = StaffDevice::whereKey($entry['device_id'])
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->exists();

        return $stillValid ? $token : null;
    }

    private static function key(string $code): string
    {
        return 'staff-app-link:'.hash('sha256', $code);
    }
}
