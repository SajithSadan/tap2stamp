<?php

use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StampLog;
use Illuminate\Database\QueryException;

test('a shop has many cards', function () {
    $shop = Shop::factory()->create();
    CustomerShopCard::factory()->count(2)->create(['shop_id' => $shop->id]);

    expect($shop->cards)->toHaveCount(2);
    expect($shop->cards->first())->toBeInstanceOf(CustomerShopCard::class);
});

test('a shop has many stamp logs', function () {
    $shop = Shop::factory()->create();
    StampLog::factory()->count(3)->create(['shop_id' => $shop->id]);

    expect($shop->stampLogs)->toHaveCount(3);
});

test('a shop slug must be unique', function () {
    Shop::factory()->create(['slug' => 'artisan-cafe']);

    expect(fn () => Shop::factory()->create(['slug' => 'artisan-cafe']))
        ->toThrow(QueryException::class);
});
