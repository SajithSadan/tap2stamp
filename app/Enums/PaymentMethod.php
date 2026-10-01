<?php

namespace App\Enums;

enum PaymentMethod: string
{
    // Paid online by the owner through Stripe Checkout.
    case Stripe = 'stripe';

    // Recorded by the admin after taking payment outside the app.
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Card (Stripe)',
            self::BankTransfer => 'Bank transfer',
            self::Cash => 'Cash',
            self::Free => 'Free',
        };
    }

    /** Shown to the owner as the payment reference, e.g. "TADA-42". */
    public static function reference(int $orderId): string
    {
        return 'TADA-'.$orderId;
    }

    /** The methods an admin can record by hand (Stripe orders only come from checkout). */
    public static function manual(): array
    {
        return [self::BankTransfer, self::Cash, self::Free];
    }
}
