<?php

namespace App\Enums;

/**
 * Where a paid order is on its way to the shop. Each stage is a timestamp
 * column on `orders`; the stage is the furthest one that's set.
 */
enum OrderStage: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Order received',
            self::Processing => 'Processing',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
        };
    }

    /** The orders column holding when this stage was reached. */
    public function column(): string
    {
        return match ($this) {
            self::Received => 'paid_at',
            self::Processing => 'processing_at',
            self::Dispatched => 'dispatched_at',
            self::Delivered => 'delivered_at',
        };
    }

    public function index(): int
    {
        return array_search($this, self::cases(), true);
    }
}
