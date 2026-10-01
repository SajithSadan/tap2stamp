<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

function productsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

test('an admin sees the products page', function () {
    $this->actingAs(productsAdmin())
        ->get('/admin/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->where('products.0.name', 'All-in-One Multi Link Stand')
            ->where('products.0.is_featured', true)
        );
});

test('an admin can add a product with a price in pounds', function () {
    $this->actingAs(productsAdmin())
        ->post('/admin/products', [
            'name' => 'QR Code Table Sticker',
            'description' => 'Epoxy-coated table sticker.',
            'price' => '5',
            'is_active' => true,
            'is_featured' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Product::where('name', 'QR Code Table Sticker')->sole())
        ->price_pence->toBe(500)
        ->is_active->toBeTrue()
        ->is_featured->toBeFalse();
});

test('an admin can change a price and switch a product off', function () {
    $product = Product::featured();

    $this->actingAs(productsAdmin())->put("/admin/products/{$product->id}", [
        'name' => $product->name,
        'description' => $product->description,
        'price' => '39.99',
        'is_active' => false,
        'is_featured' => true,
    ])->assertRedirect();

    expect($product->fresh())
        ->price_pence->toBe(3999)
        ->is_active->toBeFalse()
        ->and(Product::featured())->toBeNull();
});

test('featuring a product un-features the previous one', function () {
    $stand = Product::featured();

    $this->actingAs(productsAdmin())->post('/admin/products', [
        'name' => 'Stand v2',
        'price' => '45',
        'is_active' => true,
        'is_featured' => true,
    ]);

    expect($stand->fresh()->is_featured)->toBeFalse()
        ->and(Product::featured()->name)->toBe('Stand v2')
        ->and(Product::where('is_featured', true)->count())->toBe(1);
});

test('a product needs a name and a sensible price', function (array $data, string $field) {
    $this->actingAs(productsAdmin())
        ->post('/admin/products', [...['name' => 'Thing', 'price' => '10'], ...$data])
        ->assertSessionHasErrors($field);
})->with([
    [['name' => ''], 'name'],
    [['price' => ''], 'price'],
    [['price' => '-1'], 'price'],
    [['price' => '1.234'], 'price'],
    [['price' => 'abc'], 'price'],
]);

test('owners cannot manage products', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get('/admin/products')->assertForbidden();
    $this->actingAs($owner)->post('/admin/products', ['name' => 'X', 'price' => '1'])->assertForbidden();
});

test('price breaks charge each item at the price for its position', function () {
    $product = Product::factory()->make([
        'price_pence' => 4000,
        'price_tiers' => [['from' => 5, 'price_pence' => 1500], ['from' => 2, 'price_pence' => 2000]],
    ]);

    expect($product->totalFor(1))->toBe(4000)
        ->and($product->totalFor(2))->toBe(6000)
        ->and($product->totalFor(4))->toBe(10000)
        ->and($product->totalFor(6))->toBe(13000) // 40 + 3×20 + 2×15
        ->and($product->priceBreakdown(6))->toBe([
            ['from' => 1, 'unit_pence' => 4000, 'quantity' => 1],
            ['from' => 2, 'unit_pence' => 2000, 'quantity' => 3],
            ['from' => 5, 'unit_pence' => 1500, 'quantity' => 2],
        ]);
});

test('without price breaks every item costs the same', function () {
    $product = Product::factory()->make(['price_pence' => 4000, 'price_tiers' => null]);

    expect($product->totalFor(3))->toBe(12000);
});

test('an admin can save price breaks, sorted, and clear them again', function () {
    $product = Product::featured();
    $admin = productsAdmin();
    $save = fn (array $tiers) => $this->actingAs($admin)->put("/admin/products/{$product->id}", [
        'name' => $product->name,
        'price' => '40',
        'price_tiers' => $tiers,
        'is_active' => true,
        'is_featured' => true,
    ]);

    $save([['from' => 5, 'price' => '15'], ['from' => 2, 'price' => '20']])->assertSessionHasNoErrors();
    expect($product->fresh()->price_tiers)->toBe([
        ['from' => 2, 'price_pence' => 2000],
        ['from' => 5, 'price_pence' => 1500],
    ]);

    $save([])->assertSessionHasNoErrors();
    expect($product->fresh()->price_tiers)->toBeNull();
});

test('price breaks are checked', function (array $tiers, string $field) {
    $this->actingAs(productsAdmin())
        ->post('/admin/products', ['name' => 'Thing', 'price' => '40', 'price_tiers' => $tiers])
        ->assertSessionHasErrors($field);
})->with([
    'from the 1st item' => [[['from' => 1, 'price' => '20']], 'price_tiers.0.from'],
    'same item twice' => [[['from' => 2, 'price' => '20'], ['from' => 2, 'price' => '15']], 'price_tiers.0.from'],
    'no price' => [[['from' => 2, 'price' => '']], 'price_tiers.0.price'],
    'too many' => [array_map(fn ($i) => ['from' => $i + 2, 'price' => '1'], range(0, 5)), 'price_tiers'],
]);
