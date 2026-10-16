<?php

namespace App\Services;

use App\Models\CustomerShopCard;
use App\Models\MarketingMessage;
use App\Models\ShopWhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What Meta's webhook tells us about the shops' numbers (our Meta app is
 * subscribed to each account when it's connected):
 * - delivered / read / failed for offers (MarketingMessage) and replies;
 * - customers' messages to a shop's number (WhatsAppMessage, the owner's chats);
 * - our templates being approved or rejected (WhatsAppTemplates).
 * Trusted only with a valid X-Hub-Signature-256 (our app secret). Every
 * lookup goes through the account the message is for, so one shop's
 * webhook can never touch another shop's rows.
 */
class WhatsAppWebhook
{
    /** A customer sending one of these stops that shop's offers, like the Unsubscribe button. */
    public const STOP_WORDS = ['stop', 'unsubscribe', 'stop all'];

    public function __construct(private WhatsAppTemplates $templates, private MarketingService $marketing) {}

    public function verifySignature(string $rawBody, ?string $header): bool
    {
        $secret = (string) config('services.meta.app_secret');

        return $secret !== '' && $header !== null
            && hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $header);
    }

    public function handle(array $payload): void
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                try {
                    $this->change((string) ($entry['id'] ?? ''), $change['field'] ?? null, (array) ($change['value'] ?? []));
                } catch (Throwable $e) {
                    // One bad change never loses the rest; Meta would only resend the lot.
                    Log::error('WhatsApp webhook: change failed', ['field' => $change['field'] ?? null, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    private function change(string $wabaId, ?string $field, array $value): void
    {
        if ($field === 'message_template_status_update') {
            foreach (ShopWhatsAppAccount::where('waba_id', $wabaId)->get() as $account) {
                $this->templates->recordStatus($account, (string) ($value['message_template_name'] ?? ''), (string) ($value['event'] ?? ''));
            }

            return;
        }

        if ($field !== 'messages') {
            return;
        }

        $account = ShopWhatsAppAccount::where('phone_number_id', (string) ($value['metadata']['phone_number_id'] ?? ''))->first();
        if (! $account) {
            return;
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->status($account, (array) $status);
        }

        $profileNames = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [(string) ($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);
        foreach ($value['messages'] ?? [] as $message) {
            $this->incoming($account, (array) $message, $profileNames[(string) ($message['from'] ?? '')] ?? null);
        }
    }

    /** Sent â†’ delivered â†’ read (never back), or failed - for an offer or a reply from this shop. */
    private function status(ShopWhatsAppAccount $account, array $status): void
    {
        $id = (string) ($status['id'] ?? '');
        $state = $status['status'] ?? null;
        if ($id === '' || ! in_array($state, ['delivered', 'read', 'failed'], true)) {
            return;
        }

        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp'], config('app.timezone')) : now();
        $error = mb_substr((string) ($status['errors'][0]['error_data']['details'] ?? $status['errors'][0]['title'] ?? 'Not delivered'), 0, 250);

        $offer = MarketingMessage::where('wa_message_id', $id)
            ->whereHas('campaign', fn ($q) => $q->where('shop_id', $account->shop_id))
            ->first();

        if ($offer) {
            if ($state === 'failed') {
                $offer->update(['status' => 'failed', 'error' => $error]);
                $this->marketing->refreshCounts($offer->campaign);
            } else {
                $offer->update([
                    'delivered_at' => $offer->delivered_at ?? $at,
                    'read_at' => $state === 'read' ? ($offer->read_at ?? $at) : $offer->read_at,
                ]);
            }

            return;
        }

        $reply = WhatsAppMessage::where('wa_message_id', $id)
            ->where('shop_id', $account->shop_id)
            ->where('direction', WhatsAppMessage::OUT)
            ->first();

        if ($reply) {
            $reply->update($state === 'failed'
                ? ['status' => 'failed', 'error' => $error]
                : [
                    'status' => $state === 'read' || $reply->status === 'read' ? 'read' : 'delivered',
                    'delivered_at' => $reply->delivered_at ?? $at,
                    'read_at' => $state === 'read' ? ($reply->read_at ?? $at) : $reply->read_at,
                ]);
        }
    }

    /** A customer's message to the shop's number. Stored once, however often Meta sends it. */
    private function incoming(ShopWhatsAppAccount $account, array $message, ?string $profileName): void
    {
        $digits = preg_replace('/\D/', '', (string) ($message['from'] ?? ''));
        $type = (string) ($message['type'] ?? 'text');
        if (empty($message['id']) || strlen($digits) < 8 || in_array($type, ['reaction', 'system', 'unsupported'], true)) {
            return;
        }

        $phone = '+'.$digits;
        $body = match ($type) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? null,
            'image', 'video', 'document' => $message[$type]['caption'] ?? null,
            default => null,
        };

        $card = CustomerShopCard::where('shop_id', $account->shop_id)
            ->whereHas('customer', fn ($q) => $q->where('phone', $phone))
            ->first();

        WhatsAppMessage::firstOrCreate(['wa_message_id' => (string) $message['id']], [
            'shop_id' => $account->shop_id,
            'customer_shop_card_id' => $card?->id,
            'phone' => $phone,
            'name' => $profileName ? mb_substr($profileName, 0, 150) : null,
            'direction' => WhatsAppMessage::IN,
            'type' => mb_substr($type, 0, 20),
            'body' => $body !== null ? mb_substr((string) $body, 0, 4096) : null,
            'created_at' => isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp'], config('app.timezone')) : now(),
        ]);

        if ($card?->marketing_consent && in_array(mb_strtolower(trim((string) $body)), self::STOP_WORDS, true)) {
            $card->update(['marketing_consent' => false, 'marketing_opted_out_at' => now()]);
        }
    }
}
