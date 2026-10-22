<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;

/**
 * What the admin did with the WhatsApp button (it opens WhatsApp on their
 * own phone / computer, so the app only knows because the button reports it):
 * opened a chat, sent the welcome message or an upgrade reminder (trial
 * ending). Each one is an activity log entry (whatsapp.chat_opened /
 * welcome_sent / upgrade_sent) on the shop; the
 * owner's first welcome is also kept on their account (users.welcome_sent_at)
 * for the grids and for analysing who was welcomed and what happened next.
 */
class WhatsAppContacts
{
    public const KINDS = ['chat', 'welcome', 'upgrade'];

    public function record(string $kind, ?Shop $shop, ?User $owner): void
    {
        $shop ??= $owner?->shop;
        $owner ??= $shop?->owner;
        $who = $shop?->name ?? $owner?->name ?? 'a shop';

        ActivityLogger::record(
            match ($kind) {
                'welcome' => 'whatsapp.welcome_sent',
                'upgrade' => 'whatsapp.upgrade_sent',
                default => 'whatsapp.chat_opened',
            },
            match ($kind) {
                'welcome' => "Sent the WhatsApp welcome message to {$who}",
                'upgrade' => "Sent a WhatsApp upgrade reminder to {$who}",
                default => "Opened a WhatsApp chat with {$who}",
            },
            $shop?->id,
            $owner ?? $shop,
        );

        if ($kind === 'welcome' && $owner) {
            User::whereKey($owner->id)->whereNull('welcome_sent_at')->update(['welcome_sent_at' => now()]);
        }
    }
}
