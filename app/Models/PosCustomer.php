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

    protected $hidden = ['password', 'api_token_hash'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
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
