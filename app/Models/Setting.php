<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * App-wide admin switches (key => JSON value). Read with Setting::get(),
 * which falls back to a default until the admin first changes it.
 */
class Setting extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return $this->key;
    }

    /** Whether owners may sign up / log in with Google (also needs the .env keys). */
    public const GOOGLE_AUTH = 'google_auth_enabled';

    /** Dashboard sidebar / tab bar colours ({bg, text} hex, either null = default look). */
    public const SIDEBAR_COLORS = 'sidebar_colors';

    /** Where shops send bank transfers for orders ({account_name, sort_code, account_number}). */
    public const BANK_DETAILS = 'bank_details';

    /** The public landing page at "/": title, description, video and pricing (App\Support\LandingPage). */
    public const LANDING_PAGE = 'landing_page';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
