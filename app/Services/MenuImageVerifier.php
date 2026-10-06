<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini vision: does this photo really show this menu item? A dish needs
 * any clear photo of that dish; a branded product must match brand and size.
 * Returns null when Gemini can't be used - the caller then keeps nothing
 * (fail closed) and the item is tried again later.
 */
class MenuImageVerifier
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** @return array{match: bool, confidence: float, detected: string, reason: string}|null */
    public function verify(string $name, ?string $section, ?string $description, string $bytes, string $mime): ?array
    {
        $prompt = "You choose photos for a cafe / restaurant menu.\n"
            ."Menu item: \"{$name}\"".($section ? "\nMenu section: \"{$section}\"" : '').($description ? "\nDescription: \"{$description}\"" : '')."\n"
            .<<<'TXT'
            Does this image clearly show this menu item?
            - A freshly made dish or drink (no brand in its name, e.g. "Flat white", "Cheeseburger"): any clear,
              appetising photo of that same dish matches. A different dish, a raw ingredient, or a packaged
              product does NOT match.
            - A branded product (a brand in its name, e.g. "Coca-Cola 330ml", "KitKat"): brand, variant/flavour
              and size must match.
            - Never a match: collages, text or menus, logos, a person as the main subject, unclear or tiny food.
            Return detected = what the image actually shows, confidence from 0 to 1, and a short reason.
            TXT;

        $models = array_values(array_unique(array_filter([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ])));

        $response = null;
        foreach ($models as $model) {
            try {
                $response = Http::acceptJson()
                    ->timeout(30)
                    ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                    ->post(sprintf(self::URL, $model), [
                        'contents' => [['parts' => [
                            ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                            ['text' => $prompt],
                        ]]],
                        'generationConfig' => [
                            'temperature' => 0,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'match' => ['type' => 'BOOLEAN'],
                                    'confidence' => ['type' => 'NUMBER'],
                                    'detected' => ['type' => 'STRING'],
                                    'reason' => ['type' => 'STRING'],
                                ],
                                'required' => ['match', 'confidence', 'detected', 'reason'],
                            ],
                        ],
                    ]);
            } catch (ConnectionException $e) {
                Log::warning('Menu photo check unreachable', ['model' => $model, 'message' => $e->getMessage()]);

                return null;
            }

            // Overloaded (429 / 5xx): try the fallback model.
            if ($response->successful() || ! in_array($response->status(), [429, 500, 503], true)) {
                break;
            }
        }

        if (! $response?->successful()) {
            Log::warning('Menu photo check failed', ['status' => $response?->status(), 'error' => $response?->json('error.message')]);

            return null;
        }

        $text = collect($response->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
        $result = json_decode($text, true);

        if (! is_array($result) || ! isset($result['match'], $result['confidence'])) {
            Log::warning('Menu photo check answered badly', ['text' => mb_substr($text, 0, 300)]);

            return null;
        }

        return [
            'match' => (bool) $result['match'],
            'confidence' => max(0.0, min(1.0, (float) $result['confidence'])),
            'detected' => mb_substr((string) ($result['detected'] ?? ''), 0, 120),
            'reason' => mb_substr((string) ($result['reason'] ?? ''), 0, 300),
        ];
    }

    public function accepted(?array $verdict): bool
    {
        return $verdict !== null
            && $verdict['match']
            && $verdict['confidence'] >= (float) config('services.gemini.image_min_confidence', 0.75);
    }
}
