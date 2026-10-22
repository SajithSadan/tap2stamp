<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The only class that talks to Razorpay (plain HTTP, no SDK). Thin and
 * array-based, like StripeGateway, so tests can swap it for a mock. The key
 * secret never leaves the server: the browser only gets the key id and the
 * order id for Razorpay's payment window.
 */
class RazorpayGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function configured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    /**
     * An order for $amount (smallest unit, e.g. paise) - what the payment
     * window pays.
     *
     * @return array{id: string}
     */
    public function createOrder(int $amount, string $currency, string $receipt, array $notes = []): array
    {
        $response = $this->client()->post(self::API.'/orders', [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $notes,
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Razorpay order failed: '.($response->json('error.description') ?? $response->status()));
        }

        return ['id' => (string) $response->json('id')];
    }

    /**
     * A payment as Razorpay has it: {id, order_id, amount, currency, status
     * (created | authorized | captured | refunded | failed), amount_refunded}.
     *
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        $response = $this->client()->get(self::API.'/payments/'.rawurlencode($paymentId));

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Razorpay payment lookup failed: '.($response->json('error.description') ?? $response->status()));
        }

        return $response->json();
    }

    /**
     * Takes an authorised payment's money (accounts without automatic capture
     * - otherwise Razorpay refunds it after a few days). Returns the payment.
     *
     * @return array<string, mixed>
     */
    public function capturePayment(string $paymentId, int $amount, string $currency): array
    {
        $response = $this->client()->post(self::API.'/payments/'.rawurlencode($paymentId).'/capture', [
            'amount' => $amount,
            'currency' => $currency,
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Razorpay capture failed: '.($response->json('error.description') ?? $response->status()));
        }

        return $response->json();
    }

    /**
     * Refunds $amount (smallest unit) of a captured payment. Returns the
     * refund: {id, status: pending | processed | failed}.
     *
     * @return array<string, mixed>
     */
    public function refundPayment(string $paymentId, int $amount, array $notes = []): array
    {
        $response = $this->client()->post(self::API.'/payments/'.rawurlencode($paymentId).'/refund', [
            'amount' => $amount,
            'notes' => (object) $notes,
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Razorpay refund failed: '.($response->json('error.description') ?? $response->status()));
        }

        return $response->json();
    }

    /** The payment window's answer is genuine: HMAC of "order|payment" with the key secret. */
    public function validPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", (string) config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }

    /** A webhook really came from Razorpay: HMAC of the raw body with the webhook secret. */
    public function validWebhookSignature(string $payload, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->keyId(), (string) config('services.razorpay.key_secret'))->acceptJson()->timeout(20);
    }
}
