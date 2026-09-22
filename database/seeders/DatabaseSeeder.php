<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Dev-only login accounts (production bootstraps its one admin via
        // POST /deploy/seed-admin instead - see CLAUDE.md "Admin panel").
        // All three share the factory's default password: "password".
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@loyaltyhub.test',
            'role' => UserRole::Admin,
        ]);

        $cafeOwner = User::factory()->create([
            'name' => 'Priya Shah',
            'email' => 'owner@artisan-cafe.test',
            'role' => UserRole::Owner,
        ]);

        $barberOwner = User::factory()->create([
            'name' => 'Tom Reilly',
            'email' => 'owner@urban-barber.test',
            'role' => UserRole::Owner,
        ]);

        Shop::factory()->create([
            'user_id' => $cafeOwner->id,
            'name' => 'Artisan Cafe',
            'slug' => 'artisan-cafe',
            'max_stamps' => 6,
            'reward_title' => 'Free coffee after 6 stamps',
            'google_review_url' => 'https://g.page/r/artisan-cafe-demo/review',
            'instagram_url' => 'https://instagram.com/artisancafe',
            'wifi_ssid' => 'ArtisanCafe-Guest',
            'wifi_password' => 'latte1234',
        ]);

        Shop::factory()->create([
            'user_id' => $barberOwner->id,
            'name' => 'Urban Barber',
            'slug' => 'urban-barber',
            'max_stamps' => 8,
            'reward_title' => 'Free haircut after 8 visits',
            // Deliberately only Instagram set (no Google review, no wifi) so
            // the "tile only shows when the shop has set it" case is testable
            // alongside Artisan Cafe, which has all three.
            'google_review_url' => null,
            'instagram_url' => 'https://instagram.com/urbanbarber',
            'wifi_ssid' => null,
            'wifi_password' => null,
        ]);

        Customer::factory(5)->create();
    }
}
