<?php

namespace App\Enums;

enum OrderStatus: string
{
    // Stripe Checkout started, payment not confirmed yet.
    case Pending = 'pending';
    case Paid = 'paid';

    // Never paid and called off by the admin (e.g. the bank transfer never came).
    case Cancelled = 'cancelled';
}
