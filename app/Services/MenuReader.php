<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reads photos / PDFs of a printed menu with Gemini and returns them as our
 * menu shape (sections → items). Nothing is saved here: the admin checks the
 * result in the editor first. Server-side only - the key never reaches the browser.
 */
class MenuReader
{
    public const MAX_FILES = 5;

    /** Gemini's inline request limit is 20 MB after base64 (+33%). */
    public const MAX_TOTAL_BYTES = 14 * 1024 * 1024;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const PROMPT = <<<'TXT'
        You are reading the menu of a small UK high-street business (cafe, bakery, pub, barber...).
        The attached images / PDF pages are one menu, possibly over several pages - combine them.
        Return every section with its items, in the order they appear.
        - Section: the heading as printed (e.g. "Hot Drinks"). Items with no heading go in a section called "Menu".
        - Item name: as printed, in normal capitalisation (not ALL CAPS).
        - description: the item's description if printed, else empty.
        - price: exactly as printed, with the currency symbol if one is shown. Several sizes or options
          go in one string, e.g. "Reg £3.20 · Lg £3.80". Empty if no price.
        - tags: short labels the menu itself puts on the item (dietary marks, "New", "Bestseller",
          "Halal", "Spicy", "Contains alcohol"...), at most 6, each a few words in normal capitalisation.
          Write abbreviations and symbols out in full using the menu's own key/legend
          (e.g. "(VG)" -> "Vegan", "GF" -> "Gluten free"). Only what the menu shows - never guess.
        Never invent items, prices or descriptions. Skip addresses, opening hours and allergy notices.
        TXT;

    public function configured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return array{status: 'ok', sections: list<array<string, mixed>>}|array{status: 'unreadable'|'busy'|'unavailable'}
     */
    public function read(array $files): array
    {
        $parts = array_map(fn (UploadedFile $file) => [
            'inline_data' => [
                'mime_type' => $file->getMimeType(),
                'data' => base64_encode($file->get()),
            ],
        ], $files);
        $parts[] = ['text' => self::PROMPT];

        // Gemini answers 503 "high demand" / 429 when a model is overloaded:
        // retry once, then try the fallback model before giving up.
        $models = array_values(array_unique(array_filter([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ])));

        foreach ($models as $model) {
            try {
                $response = Http::acceptJson()
                    ->timeout(90)
                    ->retry(2, 1500, fn ($e) => $e instanceof RequestException && self::overloaded($e->response->status()), throw: false)
                    ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                    ->post(sprintf(self::URL, $model), [
                        'contents' => [['parts' => $parts]],
                        'generationConfig' => [
                            'temperature' => 0,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => self::schema(),
                        ],
                    ]);
            } catch (ConnectionException $e) {
                Log::warning('Menu reader unreachable', ['model' => $model, 'message' => $e->getMessage()]);

                return ['status' => 'unavailable'];
            }

            if ($response->successful() || ! self::overloaded($response->status())) {
                break;
            }

            Log::warning('Menu reader model busy', ['model' => $model, 'status' => $response->status()]);
        }

        if (! $response->successful()) {
            Log::warning('Menu reader failed', [
                'model' => $model,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            // 400 = Gemini couldn't use the file itself; busy = every model overloaded;
            // anything else is the key, quota or their side.
            return ['status' => match (true) {
                $response->status() === 400 => 'unreadable',
                self::overloaded($response->status()) => 'busy',
                default => 'unavailable',
            }];
        }

        $text = collect($response->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
        $sections = $this->tidy(json_decode($text, true)['sections'] ?? []);

        return $sections === [] ? ['status' => 'unreadable'] : ['status' => 'ok', 'sections' => $sections];
    }

    /**
     * Gemini's answer → the editor's shape, trimmed to our column limits,
     * unknown tags and empty rows dropped.
     *
     * @return list<array<string, mixed>>
     */
    private function tidy(mixed $sections): array
    {
        $clean = fn (mixed $value, int $max) => Str::limit(trim(is_string($value) ? $value : ''), $max - 1, '…');

        return collect(is_array($sections) ? $sections : [])
            ->filter(fn ($section) => is_array($section))
            ->map(fn (array $section) => [
                'name' => $clean($section['name'] ?? '', 80) ?: 'Menu',
                'items' => collect(is_array($section['items'] ?? null) ? $section['items'] : [])
                    ->filter(fn ($item) => is_array($item) && $clean($item['name'] ?? '', 120) !== '')
                    ->map(fn (array $item) => [
                        'name' => $clean($item['name'], 120),
                        'description' => $clean($item['description'] ?? '', 300) ?: null,
                        'price' => $clean($item['price'] ?? '', 40) ?: null,
                        'tags' => MenuItem::tidyTags($item['tags'] ?? []),
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    /** "Too busy, try later" - worth a retry or another model. */
    private static function overloaded(int $status): bool
    {
        return in_array($status, [429, 500, 503], true);
    }

    /** Structured-output schema, so Gemini answers in exactly this shape. */
    private static function schema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'sections' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'items' => [
                                'type' => 'ARRAY',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'name' => ['type' => 'STRING'],
                                        'description' => ['type' => 'STRING'],
                                        'price' => ['type' => 'STRING'],
                                        'tags' => [
                                            'type' => 'ARRAY',
                                            'items' => ['type' => 'STRING'],
                                        ],
                                    ],
                                    'required' => ['name'],
                                ],
                            ],
                        ],
                        'required' => ['name', 'items'],
                    ],
                ],
            ],
            'required' => ['sections'],
        ];
    }
}
