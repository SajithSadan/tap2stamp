<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopWhatsAppAccount;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Connecting a shop's own WhatsApp Business number, so its offers go out
 * from it (WhatsAppGateway). One number per shop: a second connect is refused
 * until the first is removed.
 *
 * - Owner, Meta's Embedded Signup popup: "new" (create / pick a WhatsApp
 *   Business account and number) or "existing" (the number on their WhatsApp
 *   Business app, Meta's coexistence). The popup gives the browser a one-time
 *   code + the account and number ids; the code is swapped here for a token
 *   with our app secret.
 * - Owner, "keys": the account id, number id and a permanent token from
 *   their own Meta app (or Meta's free test number while trying it out).
 * The admin can only remove a number, never connect one.
 *
 * Either way the ids are checked against the token with Meta before anything
 * is saved - never trusted from the browser. Failures throw
 * WhatsAppConnectException with a message safe to show.
 */
class WhatsAppConnect
{
    public const MODES = [ShopWhatsAppAccount::MODE_NEW, ShopWhatsAppAccount::MODE_EXISTING];

    public function __construct(private WhatsAppTemplates $templates) {}

    /** Our Meta app is set up for the owners' Connect popup. */
    public function embeddedSignupEnabled(): bool
    {
        return filled(config('services.meta.app_id'))
            && filled(config('services.meta.app_secret'))
            && filled(config('services.meta.embedded_signup_config_id'));
    }

    /** What the owner's page needs to open Meta's popup (nothing secret). */
    public function embeddedSignupSettings(): ?array
    {
        return $this->embeddedSignupEnabled() ? [
            'app_id' => (string) config('services.meta.app_id'),
            'config_id' => (string) config('services.meta.embedded_signup_config_id'),
            'api_version' => (string) config('services.whatsapp.api_version'),
        ] : null;
    }

    /** Owner: the popup finished - swap its code for a token and connect. */
    public function connectWithCode(Shop $shop, User $by, string $mode, string $code, string $wabaId, string $phoneNumberId): ShopWhatsAppAccount
    {
        if (! $this->embeddedSignupEnabled()) {
            throw new WhatsAppConnectException("Connecting WhatsApp isn't switched on yet.");
        }

        $response = $this->graph(fn () => Http::acceptJson()->timeout(15)->get($this->url('oauth/access_token'), [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'code' => $code,
        ]));
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            $this->logFailure('code exchange', $response);
            throw new WhatsAppConnectException("Meta didn't accept the connection. Please try again.");
        }

        return $this->connect($shop, $by, $mode, $wabaId, $phoneNumberId, $token);
    }

    /** Owner: keys from their own Meta app. */
    public function connectWithKeys(Shop $shop, User $by, string $wabaId, string $phoneNumberId, string $token): ShopWhatsAppAccount
    {
        return $this->connect($shop, $by, ShopWhatsAppAccount::MODE_KEYS, $wabaId, $phoneNumberId, $token);
    }

    /** Remove the shop's number (nothing changes at Meta; the shop can connect again). */
    public function disconnect(Shop $shop): void
    {
        $shop->whatsappAccount?->delete();
    }

    private function connect(Shop $shop, User $by, string $mode, string $wabaId, string $phoneNumberId, string $token): ShopWhatsAppAccount
    {
        if ($shop->whatsappAccount()->exists()) {
            throw new WhatsAppConnectException('This shop already has a WhatsApp number. Remove it first to connect another.');
        }

        // The number must belong to that business account, and the token must reach both.
        $numbers = $this->graph(fn () => Http::acceptJson()->timeout(15)->withToken($token)
            ->get($this->url("{$wabaId}/phone_numbers"), ['fields' => 'id,display_phone_number,verified_name']));
        if (! $numbers->successful()) {
            $this->logFailure('phone numbers', $numbers);
            throw new WhatsAppConnectException("Meta didn't accept those details. Check the business account and token.");
        }
        $number = collect($numbers->json('data', []))->firstWhere('id', $phoneNumberId);
        if (! $number) {
            throw new WhatsAppConnectException("That number isn't in this WhatsApp Business account.");
        }

        // A number added in the popup must be registered for the Cloud API before it can send.
        // The WhatsApp Business app (coexistence) and keys numbers already are.
        if ($mode === ShopWhatsAppAccount::MODE_NEW) {
            $this->register($phoneNumberId, $token);
        }

        // So Meta tells us about template approvals and delivery for this account. Not essential to send.
        $subscribed = $this->graph(fn () => Http::acceptJson()->timeout(15)->withToken($token)->post($this->url("{$wabaId}/subscribed_apps")));
        if (! $subscribed->successful()) {
            $this->logFailure('subscribe app', $subscribed);
        }

        $account = DB::transaction(function () use ($shop, $by, $mode, $wabaId, $phoneNumberId, $token, $number) {
            // Two tabs finishing at once: only one wins (shop_id is unique too).
            Shop::whereKey($shop->id)->lockForUpdate()->first();
            if ($shop->whatsappAccount()->exists()) {
                throw new WhatsAppConnectException('This shop already has a WhatsApp number. Remove it first to connect another.');
            }

            return $shop->whatsappAccount()->create([
                'mode' => $mode,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'display_phone_number' => $number['display_phone_number'] ?? null,
                'verified_name' => $number['verified_name'] ?? null,
                'access_token' => $token,
                'connected_by' => $by->id,
            ]);
        });

        // Our offer templates go onto the account straight away, so Meta starts reviewing them.
        // Never fails the connection: the page checks again while they're missing.
        try {
            $this->templates->sync($account);
        } catch (Throwable $e) {
            Log::warning('WhatsApp connect: templates failed', ['shop_id' => $shop->id, 'error' => $e->getMessage()]);
        }

        return $account;
    }

    private function register(string $phoneNumberId, string $token): void
    {
        $response = $this->graph(fn () => Http::acceptJson()->timeout(15)->withToken($token)->post($this->url("{$phoneNumberId}/register"), [
            'messaging_product' => 'whatsapp',
            // Two-step verification PIN Meta asks for when registering; not needed again by us.
            'pin' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        ]));

        // Already registered (e.g. an existing number picked in the popup) is fine: it can send.
        if (! $response->successful()) {
            $this->logFailure('register number', $response);
        }
    }

    /** @param  callable(): Response  $call */
    private function graph(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException) {
            throw new WhatsAppConnectException("Couldn't reach Meta. Please try again.");
        }
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.whatsapp.api_version').'/'.$path;
    }

    private function logFailure(string $step, Response $response): void
    {
        // Meta's message only - never the token or the app secret.
        Log::warning("WhatsApp connect: {$step} failed", [
            'status' => $response->status(),
            'error' => $response->json('error.message'),
        ]);
    }
}
