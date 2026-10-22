<?php

namespace App\Services;

use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * The only class that talks to Stripe. Kept thin and array-based so tests
 * can swap it for a mock - no real Stripe calls in the test suite.
 */
class StripeGateway
{
    public function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(array $params): array
    {
        $session = $this->client()->checkout->sessions->create($params);

        return ['id' => $session->id, 'url' => $session->url];
    }

    /**
     * A one-off Stripe coupon for one checkout's discount (our coupons live
     * in our DB), so Stripe's page and receipt show the code and the saving.
     */
    public function createCoupon(array $params): string
    {
        return $this->client()->coupons->create($params)->id;
    }

    /**
     * @return array{id: string, payment_status: string, payment_intent: ?string}
     */
    public function retrieveCheckoutSession(string $id): array
    {
        $session = $this->client()->checkout->sessions->retrieve($id);

        return [
            'id' => $session->id,
            'payment_status' => $session->payment_status,
            'payment_intent' => is_string($session->payment_intent) ? $session->payment_intent : null,
        ];
    }

    /** The card form on our page (Stripe Elements) also needs the publishable key. */
    public function elementsConfigured(): bool
    {
        return $this->configured() && filled(config('services.stripe.key'));
    }

    public function publishableKey(): string
    {
        return (string) config('services.stripe.key');
    }

    /**
     * A PaymentIntent for the card form (plan payments).
     *
     * @return array{id: string, client_secret: string}
     */
    public function createPaymentIntent(array $params): array
    {
        $intent = $this->client()->paymentIntents->create($params);

        return ['id' => $intent->id, 'client_secret' => $intent->client_secret];
    }

    /**
     * Refunds $amount (smallest unit) of a PaymentIntent. Returns the refund:
     * {id, status: pending | succeeded | failed | canceled | requires_action}.
     *
     * @return array{id: string, status: string}
     */
    public function refund(string $paymentIntent, int $amount, array $metadata = []): array
    {
        $refund = $this->client()->refunds->create(['payment_intent' => $paymentIntent, 'amount' => $amount, 'metadata' => $metadata]);

        return ['id' => $refund->id, 'status' => (string) $refund->status];
    }

    /**
     * @return array{id: string, status: string, amount: int, currency: string, latest_charge: ?string}
     */
    public function retrievePaymentIntent(string $id): array
    {
        $intent = $this->client()->paymentIntents->retrieve($id);

        return [
            'id' => $intent->id,
            'status' => $intent->status,
            'amount' => (int) $intent->amount,
            'currency' => strtoupper($intent->currency),
            'latest_charge' => is_string($intent->latest_charge) ? $intent->latest_charge : null,
        ];
    }

    /**
     * Verifies the Stripe-Signature header and returns the event as an array.
     *
     * @throws SignatureVerificationException|\UnexpectedValueException
     */
    public function webhookEvent(string $payload, string $signature): array
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'))->toArray();
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
