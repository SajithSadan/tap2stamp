<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The other application's product catalog, where menu item photos come
 * from. Every request is a GET signed with HMAC-SHA256:
 *
 *   X-Signature = hex(hmac_sha256("GET\n{path?query}\n{timestamp}\n{nonce}\n{sha256('')}", private key))
 *
 * Thin and mockable; MenuItemImages decides what to keep (after Gemini checks it).
 */
class ProductCatalog
{
    public function configured(): bool
    {
        return filled(config('services.product_api.url'))
            && filled(config('services.product_api.public_key'))
            && filled(config('services.product_api.private_key'));
    }

    /**
     * Products matching a search. An exact match answers with the image itself.
     *
     * @return list<array<string, mixed>> ['id', 'name', 'image_endpoint'] or ['direct' => true, 'bytes', 'mime', 'name']
     */
    public function search(string $query): array
    {
        $response = $this->get('/api/products?'.http_build_query(['search' => $query]), 10);

        if (! $response || $response->failed()) {
            return [];
        }

        $type = (string) $response->header('Content-Type');
        if (str_starts_with($type, 'image/')) {
            return [['direct' => true, 'bytes' => $response->body(), 'mime' => $type, 'name' => $query]];
        }

        $data = $response->json('data');
        $list = is_array($data) ? (array_is_list($data) ? $data : collect($data)->flatten(1)->all()) : [];

        return array_values(array_filter($list, fn ($product) => is_array($product) && ! empty($product['id'])));
    }

    /** @return array{0: string, 1: string}|null [bytes, Content-Type header] */
    public function image(array $candidate): ?array
    {
        if (! empty($candidate['direct'])) {
            return [$candidate['bytes'], $candidate['mime']];
        }

        if (! empty($candidate['image_endpoint'])) {
            $query = parse_url($candidate['image_endpoint'], PHP_URL_QUERY);
            $path = parse_url($candidate['image_endpoint'], PHP_URL_PATH).($query ? "?{$query}" : '');
        } else {
            $path = '/api/products/'.rawurlencode((string) $candidate['id']).'/image';
        }

        $response = $this->get($path, 15);

        return $response && $response->successful() ? [$response->body(), (string) $response->header('Content-Type')] : null;
    }

    /** The signature for one request (public for tests). */
    public static function signature(string $pathWithQuery, string $timestamp, string $nonce): string
    {
        return hash_hmac(
            'sha256',
            implode("\n", ['GET', $pathWithQuery, $timestamp, $nonce, hash('sha256', '')]),
            (string) config('services.product_api.private_key'),
        );
    }

    private function get(string $pathWithQuery, int $timeout): ?Response
    {
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();

        try {
            return Http::timeout($timeout)
                ->withOptions(['verify' => (bool) config('services.product_api.verify_ssl', true)])
                ->withHeaders([
                    'X-Public-Key' => (string) config('services.product_api.public_key'),
                    'X-Timestamp' => $timestamp,
                    'X-Nonce' => $nonce,
                    'X-Signature' => self::signature($pathWithQuery, $timestamp, $nonce),
                    'Accept' => 'application/json',
                ])
                ->get(rtrim((string) config('services.product_api.url'), '/').$pathWithQuery);
        } catch (ConnectionException $e) {
            Log::warning('Product catalog unreachable', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
