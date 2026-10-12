<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The welcome message the admin sends a newly signed-up shop on WhatsApp
 * (the WhatsApp button's "Welcome message": opens WhatsApp with it typed in,
 * from the admin's own phone / WhatsApp Web - nothing is sent by us).
 * Edited in Admin → Settings → WhatsApp (Setting::WHATSAPP_WELCOME =
 * {message, video_url}); the video link goes on its own line at the end.
 */
class WelcomeMessage
{
    public const DEFAULT_MESSAGE = "Thanks for signing up with Tada Tap.\nPlease check this video to get started.";

    public const DEFAULT_VIDEO = 'https://www.youtube.com/watch?v=xvprvHHwxyw';

    /** @return array{message: string, video_url: ?string} */
    public static function settings(): array
    {
        $saved = Setting::get(Setting::WHATSAPP_WELCOME) ?? [];

        return [
            'message' => $saved['message'] ?? self::DEFAULT_MESSAGE,
            'video_url' => array_key_exists('video_url', $saved) ? $saved['video_url'] : self::DEFAULT_VIDEO,
        ];
    }

    /** The whole message as sent: the text, then the video link. */
    public static function text(): string
    {
        $settings = self::settings();

        return trim($settings['message']."\n".($settings['video_url'] ?? ''));
    }
}
