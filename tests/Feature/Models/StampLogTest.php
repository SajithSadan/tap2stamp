<?php

use App\Enums\ActionType;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\StampLog;

test('a stamp log belongs to a customer and a shop', function () {
    $log = StampLog::factory()->create();

    expect($log->customer)->toBeInstanceOf(Customer::class);
    expect($log->shop)->toBeInstanceOf(Shop::class);
});

test('action_type is cast to the ActionType enum', function () {
    $log = StampLog::factory()->create(['action_type' => ActionType::RewardRedeemed]);

    expect($log->fresh()->action_type)->toBe(ActionType::RewardRedeemed);
});

test('stamp logs have no updated_at column', function () {
    $log = StampLog::factory()->create();

    expect($log->created_at)->not->toBeNull();
    expect($log->getAttributes())->not->toHaveKey('updated_at');
});
