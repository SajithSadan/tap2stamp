<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A restaurant using our billing desktop app (POS): its onboarding details
 * and its sign-in. The password is stored **encrypted** (APP_KEY, the
 * `encrypted` cast), not hashed, so the admin can look it up for support
 * (Admin → POS customers → Show; $customer->password). Anywhere else it is
 * only ever shared encrypted (toApi()'s `password_encrypted`).
 *
 * The app signs in with a bearer token; only its sha256 is stored.
 */
class PosCustomer extends Model
{
    /** The details the app may send and edit (not the sign-in). */
    public const DETAILS = [
        'restaurant_name',
        'contact_name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'town',
        'state',
        'postcode',
        'country',
    ];

    protected $fillable = [
        ...self::DETAILS,
        'username',
        'password',
        'password_changed_at',
    ];

    /** How long a new activation key works for, unused. */
    public const ACTIVATION_KEY_HOURS = 48;

    /** Wrong keys in a row before the key is locked (the admin makes a new one). */
    public const MAX_ACTIVATION_ATTEMPTS = 5;

    protected $hidden = ['password', 'api_token_hash', 'activation_key', 'activated_device_id'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'activation_key' => 'encrypted',
            'activation_key_expires_at' => 'datetime',
            'activation_key_used_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public static function normaliseUsername(string $username): string
    {
        return Str::lower(trim($username));
    }

    /**
     * Is $password this customer's password? Compared in constant time. A
     * password that can't be decrypted (APP_KEY changed) never matches.
     */
    public function passwordMatches(string $password): bool
    {
        try {
            return hash_equals((string) $this->password, $password);
        } catch (DecryptException) {
            Log::error('POS customer password could not be decrypted (APP_KEY changed?)', ['pos_customer' => $this->id]);

            return false;
        }
    }

    /** A new bearer token for the app (replaces the old one). Returns the plain token, shown once. */
    public function issueToken(): string
    {
        $token = Str::random(64);
        $this->forceFill(['api_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    /**
     * A new one-time activation key (replaces any unused one), as digits only,
     * e.g. "48210937". Digits because we read it out on the phone. Saved
     * encrypted; returns the plain key. The PC already activated stays
     * activated until the new key is used on a PC.
     */
    public function issueActivationKey(): string
    {
        $key = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
        $this->forceFill([
            'activation_key' => $key,
            'activation_key_expires_at' => now()->addHours(self::ACTIVATION_KEY_HOURS),
            'activation_key_used_at' => null,
            'activation_attempts' => 0,
        ])->save();

        return $key;
    }

    /** "4821-0937" from "48210937": how it is shown and read out. */
    public static function formatActivationKey(?string $key): ?string
    {
        return $key === null ? null : implode('-', str_split($key, 4));
    }

    /** What the customer typed, as stored: digits only ("4821 0937", "4821-0937" both work). */
    public static function normaliseActivationKey(string $key): string
    {
        return preg_replace('/\D+/', '', $key);
    }

    /** Is $key this customer's activation key? Constant time; an unreadable key never matches. */
    public function activationKeyMatches(string $key): bool
    {
        try {
            $stored = $this->activation_key;
        } catch (DecryptException) {
            Log::error('POS activation key could not be decrypted (APP_KEY changed?)', ['pos_customer' => $this->id]);

            return false;
        }

        return $stored !== null && $key !== '' && hash_equals((string) $stored, $key);
    }

    /** none | active | used | expired | locked: for the admin's list. */
    public function activationKeyState(): string
    {
        return match (true) {
            $this->activation_key === null => 'none',
            $this->activation_key_used_at !== null => 'used',
            $this->activation_attempts >= self::MAX_ACTIVATION_ATTEMPTS => 'locked',
            $this->activation_key_expires_at?->isPast() ?? false => 'expired',
            default => 'active',
        };
    }

    /** Is the billing app activated on this PC? */
    public function isActivatedOn(?string $deviceId): bool
    {
        return $deviceId !== null && $deviceId !== '' && $this->activated_device_id !== null
            && hash_equals($this->activated_device_id, $deviceId);
    }

    /**
     * What the app gets back: the details and the password **only in its
     * encrypted form** (as stored - only our server, with APP_KEY, can read
     * it). Never the plain password or the token hash.
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            ...$this->only(self::DETAILS),
            'username' => $this->username,
            'password_encrypted' => $this->getRawOriginal('password'),
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
