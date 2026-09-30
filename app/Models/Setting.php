<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App-wide admin switches (key => JSON value). Read with Setting::get(),
 * which falls back to a default until the admin first changes it.
 */
class Setting extends Model
{
    /** Whether owners may sign up / log in with Google (also needs the .env keys). */
    public const GOOGLE_AUTH = 'google_auth_enabled';

    /** Dashboard sidebar / tab bar colours ({bg, text} hex, either null = default look). */
    public const SIDEBAR_COLORS = 'sidebar_colors';

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
