<?php

namespace App\Services;

use App\Models\CustomerShopCard;
use App\Models\MarketingCampaign;
use App\Models\MarketingMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WhatsApp campaigns from a shop to the customers who opted in to ITS
 * offers. Hostinger has no queue workers, so a campaign is stored as one row
 * per recipient and the owner's open page sends it in small batches.
 */
class MarketingService
{
    public const AUDIENCES = ['all', 'active', 'lapsed'];

    /** "Active" = a stamp in the last this-many days; "lapsed" = none. */
    public const ACTIVE_DAYS = 30;

    public const MAX_MESSAGE = 500;

    public function __construct(private WhatsAppGateway $whatsApp) {}

    /** Opted-in cards of this shop, narrowed to an audience. */
    public function audience(Shop $shop, string $audience): Builder
    {
        $cutoff = now()->subDays(self::ACTIVE_DAYS);

        return CustomerShopCard::query()
            ->where('shop_id', $shop->id)
            ->where('marketing_consent', true)
            ->when($audience === 'active', fn ($q) => $q->where('last_stamped_at', '>=', $cutoff))
            ->when($audience === 'lapsed', fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('last_stamped_at')
                ->orWhere('last_stamped_at', '<', $cutoff)));
    }

    /** @return array<string, int> */
    public function audienceCounts(Shop $shop): array
    {
        return collect(self::AUDIENCES)->mapWithKeys(fn ($a) => [$a => $this->audience($shop, $a)->count()])->all();
    }

    /** When this shop may send its next campaign, or null for now. */
    public function nextAllowedAt(Shop $shop): ?Carbon
    {
        $last = MarketingCampaign::where('shop_id', $shop->id)->latest('id')->value('created_at');
        $next = $last ? Carbon::parse($last)->addHours(config('loyalty.marketing_cooldown_hours')) : null;

        return $next?->isFuture() ? $next : null;
    }

    /** WhatsApp template parameters can't hold line breaks, tabs or long runs of spaces. */
    public static function tidyMessage(string $message): string
    {
        return trim(preg_replace('/\s+/u', ' ', $message));
    }

    public static function firstName(string $name): string
    {
        return Str::of($name)->trim()->explode(' ')->first() ?: 'there';
    }

    /** Creates the campaign with a pending message for everyone in the audience. */
    public function start(Shop $shop, User $sender, string $audience, string $message, ?string $imagePath = null): MarketingCampaign
    {
        return DB::transaction(function () use ($shop, $sender, $audience, $message, $imagePath) {
            $cardIds = $this->audience($shop, $audience)->pluck('id');

            $campaign = MarketingCampaign::create([
                'shop_id' => $shop->id,
                'user_id' => $sender->id,
                'audience' => $audience,
                'message' => self::tidyMessage($message),
                'image_path' => $imagePath,
                'recipients_count' => $cardIds->count(),
            ]);

            $now = now();
            foreach ($cardIds->chunk(500) as $chunk) {
                MarketingMessage::insert($chunk->map(fn ($id) => [
                    'marketing_campaign_id' => $campaign->id,
                    'customer_shop_card_id' => $id,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }

            return $campaign;
        });
    }

    /**
     * Sends the next few messages. Each is claimed (pending → sending) under
     * a lock first, so two open tabs can't send the same message twice.
     *
     * @return array{sent: int, failed: int, total: int, done: bool}
     */
    public function sendBatch(MarketingCampaign $campaign): array
    {
        $claimed = DB::transaction(function () use ($campaign) {
            // A batch that died mid-way (closed tab, timeout) leaves claims
            // behind. They may have gone out, so never retry them - a
            // customer must not get the same offer twice.
            $campaign->messages()
                ->where('status', 'sending')
                ->where('updated_at', '<', now()->subMinutes(5))
                ->update(['status' => 'failed', 'error' => 'Interrupted - may not have been sent']);

            $messages = $campaign->messages()
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(config('loyalty.marketing_batch_size'))
                ->lockForUpdate()
                ->get();

            MarketingMessage::whereKey($messages->modelKeys())->update(['status' => 'sending']);

            return $messages;
        });

        $shop = $campaign->shop;
        $posterUrl = $campaign->posterUrl();
        $claimed->load('card.customer');

        foreach ($claimed as $message) {
            $card = $message->card;

            // Unsubscribed since the campaign started: skip, never send.
            if (! $card->marketing_consent) {
                $message->update(['status' => 'failed', 'error' => 'Unsubscribed']);

                continue;
            }

            $result = $this->whatsApp->sendTemplate(
                $card->customer->phone,
                [self::firstName($card->customer->name), $shop->name, $campaign->message],
                $card->unsubscribeToken(),
                $posterUrl,
            );

            $message->update($result['ok']
                ? ['status' => 'sent', 'wa_message_id' => $result['id'], 'sent_at' => now()]
                : ['status' => 'failed', 'error' => $result['error']]);
        }

        return $this->refreshCounts($campaign);
    }

    /** @return array{sent: int, failed: int, total: int, done: bool} */
    private function refreshCounts(MarketingCampaign $campaign): array
    {
        $counts = $campaign->messages()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $done = ! ($counts['pending'] ?? 0) && ! ($counts['sending'] ?? 0);

        $campaign->update([
            'sent_count' => $counts['sent'] ?? 0,
            'failed_count' => $counts['failed'] ?? 0,
            'status' => $done ? 'sent' : 'sending',
            'completed_at' => $done ? ($campaign->completed_at ?? now()) : null,
        ]);

        return [
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'total' => $campaign->recipients_count,
            'done' => $done,
        ];
    }
}
