<?php

namespace App\Services;

use App\Models\ShopWhatsAppAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Our two offer templates (WhatsAppGateway::TEMPLATE_BODY: plain, and with a
 * poster as an Image header) on each shop's own WhatsApp Business account.
 * Meta only sends marketing messages through templates it has approved on
 * that account, so they're created when a number is connected, and their
 * status is kept in shop_whatsapp_accounts.templates: Meta's own status
 * (APPROVED, PENDING, REJECTED, PAUSED…) or NOT_CREATED when Meta refused.
 * Updated by Meta's webhook (WhatsAppWebhook) and by a check when the owner
 * opens the page while one is still waiting.
 */
class WhatsAppTemplates
{
    public const APPROVED = 'APPROVED';

    public const NOT_CREATED = 'NOT_CREATED';

    /** A waiting template is checked with Meta at most this often from page loads. */
    public const RECHECK_MINUTES = 10;

    /** Sample values Meta's reviewers see for {{1}}…{{3}}. */
    private const EXAMPLE_BODY = ['Sam', 'Bean There', 'Half-price cakes after 3pm this Friday - show this message at the till.'];

    /** @return array{offer: string, poster: string} our template names */
    public function names(): array
    {
        return [
            'offer' => (string) config('services.whatsapp.template'),
            'poster' => (string) config('services.whatsapp.template_image'),
        ];
    }

    public function nameFor(bool $withPoster): string
    {
        return $this->names()[$withPoster ? 'poster' : 'offer'];
    }

    public function ready(ShopWhatsAppAccount $account, bool $withPoster): bool
    {
        return $account->templateStatus($this->nameFor($withPoster)) === self::APPROVED;
    }

    /**
     * Looks both templates up on the account and creates any that's missing
     * (or that Meta refused before). Meta failures are logged, never thrown.
     *
     * @return array<string, string> name => status
     */
    public function sync(ShopWhatsAppAccount $account): array
    {
        $statuses = $account->templates ?? [];

        foreach ($this->names() as $kind => $name) {
            $found = $this->lookUp($account, $name);
            if ($found === false) {
                continue; // Meta unreachable: keep what we knew
            }

            $statuses[$name] = $found ?? $this->create($account, $name, $kind === 'poster');
        }

        $account->update(['templates' => $statuses, 'templates_checked_at' => now()]);

        return $statuses;
    }

    /** From a page load: only while a template isn't approved, and not more than every few minutes. */
    public function refreshIfWaiting(ShopWhatsAppAccount $account): void
    {
        $waiting = collect($this->names())->contains(fn ($name) => $account->templateStatus($name) !== self::APPROVED);
        $recent = $account->templates_checked_at?->gt(now()->subMinutes(self::RECHECK_MINUTES));

        if (! $waiting || $recent) {
            return;
        }

        try {
            $this->sync($account);
        } catch (Throwable $e) {
            Log::warning('WhatsApp templates: check failed', ['shop_id' => $account->shop_id, 'error' => $e->getMessage()]);
        }
    }

    /** Meta's webhook: a template's status changed. */
    public function recordStatus(ShopWhatsAppAccount $account, string $name, string $status): void
    {
        if (! in_array($name, $this->names(), true)) {
            return; // one of the owner's own templates
        }

        $account->update(['templates' => [...($account->templates ?? []), $name => strtoupper($status)]]);
    }

    /** @return string|null|false Meta's status, null = not on the account, false = couldn't ask */
    private function lookUp(ShopWhatsAppAccount $account, string $name): string|null|false
    {
        $response = $this->graph(fn () => Http::acceptJson()->timeout(15)->withToken($account->access_token)
            ->get($this->url("{$account->waba_id}/message_templates"), ['name' => $name, 'fields' => 'name,status,language', 'limit' => 50]));

        if (! $response?->successful()) {
            $this->logFailure('look up', $response);

            return false;
        }

        $template = collect($response->json('data', []))
            ->first(fn ($t) => ($t['name'] ?? null) === $name && ($t['language'] ?? null) === config('services.whatsapp.template_language'));

        return $template ? strtoupper((string) ($template['status'] ?? 'PENDING')) : null;
    }

    private function create(ShopWhatsAppAccount $account, string $name, bool $withPoster): string
    {
        $components = [
            [
                'type' => 'BODY',
                'text' => WhatsAppGateway::TEMPLATE_BODY,
                'example' => ['body_text' => [self::EXAMPLE_BODY]],
            ],
            [
                'type' => 'BUTTONS',
                'buttons' => [[
                    'type' => 'URL',
                    'text' => WhatsAppGateway::BUTTON_LABEL,
                    'url' => rtrim((string) config('app.url'), '/').'/u/{{1}}',
                    'example' => [rtrim((string) config('app.url'), '/').'/u/'.str_repeat('a1b2', 8)],
                ]],
            ],
        ];

        if ($withPoster) {
            $handle = $this->sampleImageHandle($account);
            if (! $handle) {
                return self::NOT_CREATED;
            }
            array_unshift($components, ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => [$handle]]]);
        }

        $response = $this->graph(fn () => Http::acceptJson()->timeout(20)->withToken($account->access_token)
            ->post($this->url("{$account->waba_id}/message_templates"), [
                'name' => $name,
                'language' => config('services.whatsapp.template_language'),
                'category' => 'MARKETING',
                'components' => $components,
            ]));

        if (! $response?->successful() || ! $response->json('id')) {
            $this->logFailure("create {$name}", $response);

            return self::NOT_CREATED;
        }

        return strtoupper((string) ($response->json('status') ?? 'PENDING'));
    }

    /**
     * An Image-header template needs a sample image, uploaded with Meta's
     * Resumable Upload API to the app the token belongs to (ours, or the
     * owner's own for "keys").
     */
    private function sampleImageHandle(ShopWhatsAppAccount $account): ?string
    {
        $path = public_path('images/og-image.jpg');
        $bytes = is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            return null;
        }

        $appId = $this->graph(fn () => Http::acceptJson()->timeout(15)->withToken($account->access_token)->get($this->url('app')))?->json('id');
        if (! $appId) {
            return null;
        }

        $session = $this->graph(fn () => Http::acceptJson()->timeout(15)->withToken($account->access_token)
            ->post($this->url("{$appId}/uploads").'?'.http_build_query([
                'file_name' => 'offer-sample.jpg', 'file_length' => strlen($bytes), 'file_type' => 'image/jpeg',
            ])))?->json('id');
        if (! $session) {
            return null;
        }

        $upload = $this->graph(fn () => Http::acceptJson()->timeout(30)
            ->withHeaders(['Authorization' => 'OAuth '.$account->access_token, 'file_offset' => '0'])
            ->withBody($bytes, 'image/jpeg')
            ->post($this->url($session)));

        if (! $upload?->successful() || ! $upload->json('h')) {
            $this->logFailure('sample image upload', $upload);

            return null;
        }

        return (string) $upload->json('h');
    }

    /** @param  callable(): Response  $call */
    private function graph(callable $call): ?Response
    {
        try {
            return $call();
        } catch (ConnectionException) {
            return null;
        }
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.whatsapp.api_version').'/'.$path;
    }

    private function logFailure(string $step, ?Response $response): void
    {
        // Meta's message only - never the token.
        Log::warning("WhatsApp templates: {$step} failed", [
            'status' => $response?->status(),
            'error' => $response?->json('error.error_user_msg') ?? $response?->json('error.message'),
        ]);
    }
}
