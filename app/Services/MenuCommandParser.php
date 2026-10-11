<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Chicken theernnu" / "Disable fish items" / "Meals available now" → which
 * of the shop's menu items, and whether to mark them available or not
 * available. Gemini only ever sees each item's id + name (no prices,
 * descriptions or photos) and the command; it changes nothing - the result
 * is shown to the person to confirm, then applied with
 * ShopMenu::setAvailability(). Uses the same key / models as MenuReader.
 */
class MenuCommandParser
{
    public const MAX_LENGTH = 200;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const INSTRUCTION = <<<'TXT'
You are a smart inventory assistant for a restaurant. Your job is to parse the user's quick natural language command (which can be in English, Malayalam, or Manglish) and identify which menu items match the request. Determine whether the items should be marked unavailable (disabled) or available (enabled). Return only the matched item IDs and the intended target status.

Rules:
- Only use ids from the menu list you are given. Never invent ids.
- Match by meaning, not just exact words: "chicken" matches "Chicken Biryani" and "Chicken 65"; "fish items" matches every fish dish; "meals" matches meal/thali items.
- Malayalam / Manglish: "theernnu", "theerunnu", "kazhinju", "illa", "stock illa", "out" mean sold out (disable); "undu", "vannu", "ready", "available", "kittum" mean available again (enable).
- If nothing on the menu matches, return an empty matched_item_ids list and say so in the explanation.
- explanation: one short sentence in English for the restaurant staff, e.g. "Disabled 2 chicken items based on your command."
TXT;

    public function configured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @return array{status: 'ok', availability: string, items: list<array{id: int, name: string, availability: string}>, explanation: string}
     *                                                                                                                                         |array{status: 'empty'|'busy'|'unavailable'}
     */
    public function parse(Shop $shop, string $command): array
    {
        $items = $shop->menuItems()
            ->select('menu_items.id', 'menu_items.name', 'menu_items.availability')
            ->get();

        if ($items->isEmpty()) {
            return ['status' => 'empty'];
        }

        // The smallest useful payload: id + name only.
        $menu = $items->map(fn (MenuItem $item) => ['id' => $item->id, 'name' => $item->name])->values()->all();

        $response = $this->ask(
            $shop->id,
            'Menu items (JSON): '.json_encode($menu, JSON_UNESCAPED_UNICODE)."\n\nCommand: ".Str::limit(trim($command), self::MAX_LENGTH, '')
        );

        if (! $response instanceof Response) {
            return ['status' => $response];
        }

        $text = collect($response->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
        $answer = json_decode($text, true);
        $answer = is_array($answer) ? $answer : [];

        // Never trust ids from the model: keep only this shop's.
        $ids = collect(is_array($answer['matched_item_ids'] ?? null) ? $answer['matched_item_ids'] : [])
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique();
        $matched = $items->whereIn('id', $ids->all())->values();

        return [
            'status' => 'ok',
            'availability' => ($answer['action'] ?? null) === 'enable' ? MenuItem::AVAILABLE : MenuItem::UNAVAILABLE,
            'items' => $matched->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'availability' => $item->availability,
            ])->all(),
            'explanation' => Str::limit(trim(is_string($answer['explanation'] ?? null) ? $answer['explanation'] : ''), 200),
        ];
    }

    /**
     * The Gemini call, with MenuReader's busy handling: retry once, then the
     * fallback model. A Response on success, else 'busy' / 'unavailable'.
     */
    private function ask(int $shopId, string $prompt): Response|string
    {
        $started = microtime(true);
        $response = null;
        $model = null;

        $models = array_values(array_unique(array_filter([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ])));

        foreach ($models as $model) {
            try {
                $response = Http::acceptJson()
                    ->timeout(30)
                    ->retry(2, 1000, fn ($e) => $e instanceof RequestException && self::overloaded($e->response->status()), throw: false)
                    ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                    ->post(sprintf(self::URL, $model), [
                        'systemInstruction' => ['parts' => [['text' => self::INSTRUCTION]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature' => 0,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => self::schema(),
                        ],
                    ]);
            } catch (ConnectionException $e) {
                Log::warning('Menu command: Gemini unreachable', ['model' => $model, 'message' => $e->getMessage()]);
                AiUsage::record('stock_command', null, $model, $started, $shopId);

                return 'unavailable';
            }

            if ($response->successful()) {
                AiUsage::record('stock_command', $response, $model, $started, $shopId);

                return $response;
            }

            if (! self::overloaded($response->status())) {
                break;
            }

            Log::warning('Menu command: model busy', ['model' => $model, 'status' => $response->status()]);
        }

        Log::warning('Menu command failed', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);
        AiUsage::record('stock_command', $response, $model, $started, $shopId);

        return self::overloaded($response->status()) ? 'busy' : 'unavailable';
    }

    private static function overloaded(int $status): bool
    {
        return in_array($status, [429, 500, 503], true);
    }

    /** Structured output: exactly this shape back. */
    private static function schema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'action' => ['type' => 'STRING', 'enum' => ['disable', 'enable']],
                'matched_item_ids' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                'explanation' => ['type' => 'STRING'],
            ],
            'required' => ['action', 'matched_item_ids', 'explanation'],
        ];
    }
}
