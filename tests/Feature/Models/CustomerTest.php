<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use Illuminate\Database\QueryException;

test('a uuid is auto-generated on creation', function () {
    $customer = Customer::factory()->create();

    expect($customer->uuid)->not->toBeNull();
    expect(strlen($customer->uuid))->toBe(36);
});

test('an explicitly provided uuid is not overwritten', function () {
    $customer = Customer::factory()->create(['uuid' => '11111111-1111-1111-1111-111111111111']);

    expect($customer->uuid)->toBe('11111111-1111-1111-1111-111111111111');
});

test('a customer phone must be unique', function () {
    Customer::factory()->create(['phone' => '+447700900123']);

    expect(fn () => Customer::factory()->create(['phone' => '+447700900123']))
        ->toThrow(QueryException::class);
});

test('a customer has many cards', function () {
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->count(2)->create(['customer_id' => $customer->id]);

    expect($customer->cards)->toHaveCount(2);
});
