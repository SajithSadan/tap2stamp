<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\MarketingMessage;
use App\Models\Shop;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The owner's WhatsApp chats: what customers send to the shop's number
 * (from Meta's webhook), with the offers they were sent, and the owner's
 * replies. Always one shop's rows only. WhatsApp allows a free-text reply
 * only within 24 hours of the customer's last message; after that only an
 * approved template (an offer) can go.
 */
class WhatsAppInbox
{
    public const REPLY_WINDOW_HOURS = 24;

    public const MAX_REPLY = 1000;

    public const CHATS_SHOWN = 50;

    public function __construct(private WhatsAppGateway $whatsApp) {}

    /** "+447700900123" from any way of writing it, or null. */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    public function unreadCount(Shop $shop): int
    {
        return WhatsAppMessage::where('shop_id', $shop->id)
            ->where('direction', WhatsAppMessage::IN)
            ->whereNull('seen_at')
            ->count();
    }

    /** One row per number, newest chat first. */
    public function conversations(Shop $shop): array
    {
        $rows = WhatsAppMessage::where('shop_id', $shop->id)
            ->selectRaw("phone, max(id) as last_id, sum(case when direction = 'in' and seen_at is null then 1 else 0 end) as unread")
            ->groupBy('phone')
            ->orderByDesc('last_id')
            ->limit(self::CHATS_SHOWN)
            ->get();

        $last = WhatsAppMessage::whereKey($rows->pluck('last_id'))->get()->keyBy('id');
        $names = $this->names($shop, $rows->pluck('phone'));

        return $rows->map(function ($row) use ($last, $names) {
            $message = $last[$row->last_id];

            return [
                'phone' => $row->phone,
                'name' => $names[$row->phone] ?? null,
                'unread' => (int) $row->unread,
                'last' => ($message->direction === WhatsAppMessage::OUT ? 'You: ' : '').self::preview($message),
                'at' => self::shortTime($message->created_at),
            ];
        })->all();
    }

    /** One chat: its messages and the offers this customer was sent, oldest first. */
    public function thread(Shop $shop, string $phone): array
    {
        // The last 100, oldest first (so messages in the same second keep their order).
        $messages = WhatsAppMessage::where('shop_id', $shop->id)->where('phone', $phone)->latest('id')->limit(100)->get()->reverse();
        $card = $this->card($shop, $phone);

        $offers = $card
            ? MarketingMessage::where('customer_shop_card_id', $card->id)
                ->whereNotNull('sent_at')
                ->with('campaign')
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        $items = $messages->map(fn (WhatsAppMessage $m) => [
            'id' => 'm'.$m->id,
            'kind' => 'message',
            'direction' => $m->direction,
            'body' => self::preview($m),
            'poster_url' => null,
            'status' => $m->direction === WhatsAppMessage::OUT ? $this->tick($m->status, $m->delivered_at, $m->read_at) : null,
            'error' => $m->error,
            'sort' => $m->created_at,
        ])->concat($offers->map(fn (MarketingMessage $o) => [
            'id' => 'o'.$o->id,
            'kind' => 'offer',
            'direction' => WhatsAppMessage::OUT,
            'body' => $o->campaign->message,
            'poster_url' => $o->campaign->posterUrl(),
            'status' => $this->tick($o->status, $o->delivered_at, $o->read_at),
            'error' => $o->status === 'failed' ? $o->error : null,
            'sort' => $o->sent_at,
        ]))->sortBy('sort')->values()->map(fn ($item) => [
            ...collect($item)->except('sort')->all(),
            'time' => $item['sort']->timezone('Europe/London')->format('j M, g:i A'),
        ]);

        $lastIn = $this->lastIncomingAt($shop, $phone);
        $replyUntil = $lastIn?->copy()->addHours(self::REPLY_WINDOW_HOURS);

        return [
            'phone' => $phone,
            'name' => $this->names($shop, collect([$phone]))[$phone] ?? null,
            'customer' => $card !== null,
            'opted_in' => (bool) $card?->marketing_consent,
            'can_reply' => $replyUntil?->isFuture() ?? false,
            'reply_until' => $replyUntil?->isFuture() ? $replyUntil->timezone('Europe/London')->format('D g:i A') : null,
            'messages' => $items->all(),
        ];
    }

    public function markSeen(Shop $shop, string $phone): void
    {
        WhatsAppMessage::where('shop_id', $shop->id)
            ->where('phone', $phone)
            ->where('direction', WhatsAppMessage::IN)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);
    }

    /** The owner's text reply, from the shop's own number. A failed send is kept (and shown) too. */
    public function reply(Shop $shop, User $by, string $phone, string $text): WhatsAppMessage
    {
        $account = $shop->whatsappAccount;
        if (! $account) {
            throw ValidationException::withMessages(['body' => 'Connect your WhatsApp number first.']);
        }

        $lastIn = $this->lastIncomingAt($shop, $phone);
        if (! $lastIn || $lastIn->lt(now()->subHours(self::REPLY_WINDOW_HOURS))) {
            throw ValidationException::withMessages(['body' => 'WhatsApp only allows a reply within 24 hours of their last message.']);
        }

        $result = $this->whatsApp->sendText($account, $phone, $text);
        $card = $this->card($shop, $phone);

        $reply = WhatsAppMessage::create([
            'shop_id' => $shop->id,
            'customer_shop_card_id' => $card?->id,
            'phone' => $phone,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => $text,
            'wa_message_id' => $result['id'] ?? null,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'error' => $result['ok'] ? null : $result['error'],
            'user_id' => $by->id,
        ]);

        if ($result['ok']) {
            ActivityLogger::record(
                'whatsapp.replied',
                'Replied on WhatsApp to '.($card?->customer?->name ?? 'a customer'),
                $shop->id,
            );
        }

        return $reply;
    }

    private function lastIncomingAt(Shop $shop, string $phone): ?Carbon
    {
        $at = WhatsAppMessage::where('shop_id', $shop->id)
            ->where('phone', $phone)
            ->where('direction', WhatsAppMessage::IN)
            ->max('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    private function card(Shop $shop, string $phone): ?CustomerShopCard
    {
        return CustomerShopCard::where('shop_id', $shop->id)
            ->whereHas('customer', fn ($q) => $q->where('phone', $phone))
            ->with('customer')
            ->first();
    }

    /**
     * The customer's name at this shop, else their WhatsApp profile name.
     *
     * @param  Collection<int, string>  $phones
     * @return array<string, string>
     */
    private function names(Shop $shop, Collection $phones): array
    {
        $profile = WhatsAppMessage::where('shop_id', $shop->id)
            ->whereIn('phone', $phones)
            ->whereNotNull('name')
            ->orderBy('id')
            ->pluck('name', 'phone');

        $customers = Customer::whereIn('phone', $phones)
            ->whereHas('cards', fn ($q) => $q->where('shop_id', $shop->id))
            ->pluck('name', 'phone');

        return [...$profile->all(), ...$customers->all()];
    }

    private function tick(?string $status, $deliveredAt, $readAt): string
    {
        return match (true) {
            $status === 'failed' => 'failed',
            $readAt !== null => 'read',
            $deliveredAt !== null => 'delivered',
            default => 'sent',
        };
    }

    private static function preview(WhatsAppMessage $m): string
    {
        $label = ['image' => 'Photo', 'video' => 'Video', 'audio' => 'Voice message', 'document' => 'Document', 'sticker' => 'Sticker', 'location' => 'Location', 'contacts' => 'Contact'][$m->type] ?? null;

        return match (true) {
            $label !== null && filled($m->body) => "[{$label}] {$m->body}",
            $label !== null => "[{$label}]",
            default => (string) $m->body,
        };
    }

    private static function shortTime(Carbon $at): string
    {
        $local = $at->copy()->timezone('Europe/London');

        return $local->isToday() ? $local->format('g:i A') : $local->format('j M');
    }
}
