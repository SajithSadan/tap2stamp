<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What shops can buy from us (the counter display stand, later more).
        // Prices in pence, the amount actually charged.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_pence');
            $table->boolean('is_active')->default(true);
            // The one product the owner dashboard offers ("What's next?").
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        // One product per order. Name and price are copied at order time, so
        // later price changes never rewrite past orders.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Who placed it: the owner (Stripe) or the admin (manual payment).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('total_pence');
            $table->string('payment_method'); // stripe | bank_transfer | cash | free
            $table->string('status')->default('pending'); // pending | paid
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
        });

        // Set by a shop's first paid/recorded order; hides the "order your
        // counter display" banner for good.
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('product_ordered_at')->nullable()->after('logo_path');
        });

        // Production has no SSH to run a seeder, so the launch product comes
        // with the schema. The admin edits it under Orders → Products.
        DB::table('products')->insert([
            'name' => 'All-in-One Multi Link Stand',
            'description' => 'Counter display that works with NFC tap or QR scan. Includes your first 12 months on the Starter plan free.',
            'price_pence' => 4000,
            'is_active' => true,
            'is_featured' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('product_ordered_at');
        });
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
