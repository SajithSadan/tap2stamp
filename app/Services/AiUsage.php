<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records every Gemini request - who (ActivityLogger::actor(): staff, owner or
 * admin), for which shop, which feature and model, the outcome and the tokens
 * Gemini reports (usageMetadata) - for the admin's AI usage page. A failure
 * to record is logged, never thrown: it must not break the feature.
 */
class AiUsage
{
    /**
     * @param  string  $feature  a key of AiUsageLog::FEATURES
     * @param  Response|null  $response  Gemini's last answer (null = never reached)
     * @param  float  $startedAt  microtime(true) before the first attempt
     * @param  int|null  $shopId  null = the page's shop, else the signed-in owner's / staff's
     */
    public static function record(string $feature, ?Response $response, ?string $model, float $startedAt, ?int $shopId = null): void
    {
        try {
            $actor = ActivityLogger::actor() ?? [];
            $usage = $response?->json('usageMetadata') ?? [];
            $input = (int) ($usage['promptTokenCount'] ?? 0);
            // Thinking models bill their "thoughts" as output too.
            $output = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);

            AiUsageLog::create([
                'feature' => $feature,
                'shop_id' => $shopId ?? self::pageShopId() ?? ($actor['shop_id'] ?? null),
                'actor_type' => $actor['actor_type'] ?? null,
                'user_id' => $actor['user_id'] ?? null,
                'staff_member_id' => $actor['staff_member_id'] ?? null,
                'actor_name' => $actor['actor_name'] ?? null,
                'model' => $model,
                'status' => match (true) {
                    $response === null => 'unreachable',
                    $response->successful() => 'ok',
                    in_array($response->status(), [429, 500, 503], true) => 'busy',
                    default => 'failed',
                },
                'input_tokens' => $input,
                'output_tokens' => $output,
                'total_tokens' => (int) ($usage['totalTokenCount'] ?? $input + $output),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('AI usage not recorded', ['feature' => $feature, 'message' => $e->getMessage()]);
        }
    }

    /** /admin/shops/{shop}/… - the shop the admin is working on. */
    private static function pageShopId(): ?int
    {
        $shop = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->route('shop');

        return match (true) {
            $shop instanceof Shop => $shop->id,
            is_numeric($shop) => (int) $shop,
            default => null,
        };
    }
}
