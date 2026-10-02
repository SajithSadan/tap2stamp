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
