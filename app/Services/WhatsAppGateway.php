<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the WhatsApp Cloud API (Meta), so tests can mock it.
 *
 * Marketing messages must use a Meta-approved template. Register it in
 * WhatsApp Manager as category "Marketing", named WHATSAPP_TEMPLATE, with
 * exactly this body, and one "Visit website" button labelled "Unsubscribe"
 * with the dynamic URL {APP_URL}/u/{{1}}.
 */
class WhatsAppGateway
{
    /** {{1}} first name, {{2}} shop name, {{3}} the owner's message. Mirrored in the owner's preview. */
    public const TEMPLATE_BODY = "Hi {{1}}, a message from {{2}}:\n\n{{3}}\n\nYou're getting this because you joined their loyalty card. Tap Unsubscribe to stop.";

    public const BUTTON_LABEL = 'Unsubscribe';

    public function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * With a poster, the second template is used (WHATSAPP_TEMPLATE_IMAGE):
     * same body and button, plus an Image header. A template's header type is
     * fixed when Meta approves it, so one template can't do both.
     *
     * @param  list<string>  $body  the template's {{1}}…{{3}}
     * @param  string|null  $imageUrl  public https URL of the poster (Meta downloads it)
     * @return array{ok: bool, id?: string, error?: string}
     */
    public function sendTemplate(string $phone, array $body, string $unsubscribeToken, ?string $imageUrl = null): array
    {
        $components = [
            [
                'type' => 'body',
                'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $body),
            ],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $unsubscribeToken]],
            ],
        ];

        if ($imageUrl) {
            array_unshift($components, [
                'type' => 'header',
                'parameters' => [['type' => 'image', 'image' => ['link' => $imageUrl]]],
            ]);
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version'),
            config('services.whatsapp.phone_number_id'),
        );

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->withToken((string) config('services.whatsapp.token'))
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    // International format without the "+".
                    'to' => ltrim($phone, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => config($imageUrl ? 'services.whatsapp.template_image' : 'services.whatsapp.template'),
                        'language' => ['code' => config('services.whatsapp.template_language')],
                        'components' => $components,
                    ],
                ]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'WhatsApp unreachable'];
        }

        if ($response->successful() && $id = $response->json('messages.0.id')) {
            return ['ok' => true, 'id' => $id];
        }

        return ['ok' => false, 'error' => mb_substr((string) ($response->json('error.message') ?? "HTTP {$response->status()}"), 0, 250)];
    }
}
