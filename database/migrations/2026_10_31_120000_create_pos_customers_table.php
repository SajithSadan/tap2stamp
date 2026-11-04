<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Restaurants using our billing desktop app (POS), signed up from the app
        // through /api/pos. Not linked to loyalty shops.
        Schema::create('pos_customers', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_name');
            $table->string('contact_name');
            $table->string('email')->nullable();
            $table->string('phone', 32);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('town');
            $table->string('state')->nullable();
            $table->string('postcode', 16)->nullable();
            $table->char('country', 2)->default('IN');
            // Lower-case, unique: what they sign in with.
            $table->string('username', 32)->unique();
            // Encrypted with APP_KEY (Laravel's `encrypted` cast), not hashed: we
            // can read it back. Never sent in an API response.
            $table->text('password');
            $table->timestamp('password_changed_at')->nullable();
            // The app's bearer token: only its sha256 is kept (like staff devices).
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_customers');
    }
};
