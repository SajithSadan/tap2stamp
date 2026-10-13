<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One structured Gemini request (JSON in, JSON out) for the admin CRM's AI
 * (CrmBriefing, CallPrep): the same key, models and busy handling as the menu
 * AI (retry, then the fallback model), and every request recorded by
 * AiUsage::record(). The callers send per-shop figures and the admin's own
 * notes only - never customer names or phone numbers.
 */
class Gemini
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function configured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @param  array<string, mixed>  $schema  Gemini responseSchema
     * @return array<string, mixed>|string the decoded answer, or 'busy' / 'unavailable'
     */
    public function json(string $feature, string $instruction, string $prompt, array $schema, ?int $shopId = null): array|string
    {
        if (! $this->configured()) {
            return 'unavailable';
        }

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
                    ->timeout(60)
                    ->retry(2, 1000, fn ($e) => $e instanceof RequestException && self::overloaded($e->response->status()), throw: false)
                    ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                    ->post(sprintf(self::URL, $model), [
                        'systemInstruction' => ['parts' => [['text' => $instruction]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature' => 0.3,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => $schema,
                        ],
                    ]);
            } catch (ConnectionException $e) {
                Log::warning("Gemini ({$feature}) unreachable", ['model' => $model, 'message' => $e->getMessage()]);
                AiUsage::record($feature, null, $model, $started, $shopId);

                return 'unavailable';
            }

            if ($response->successful()) {
                AiUsage::record($feature, $response, $model, $started, $shopId);
                $text = collect($response->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
                $answer = json_decode($text, true);

                return is_array($answer) ? $answer : 'unavailable';
            }

            if (! self::overloaded($response->status())) {
                break;
            }
        }

        Log::warning("Gemini ({$feature}) failed", ['model' => $model, 'status' => $response?->status(), 'error' => $response?->json('error.message')]);
        AiUsage::record($feature, $response instanceof Response ? $response : null, $model, $started, $shopId);

        return $response && self::overloaded($response->status()) ? 'busy' : 'unavailable';
    }

    private static function overloaded(int $status): bool
    {
        return in_array($status, [429, 500, 503], true);
    }
}
