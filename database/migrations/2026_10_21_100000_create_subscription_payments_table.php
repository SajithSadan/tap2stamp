<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per attempt to pay for a year of a plan: Stripe (UK) or Razorpay (elsewhere).
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // Who pressed Pay (the owner).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name', 60);
            $table->string('provider', 20);
            $table->char('currency', 3);
            // In the currency's smallest unit (pence, paise).
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            // Stripe PaymentIntent id / Razorpay order id.
            $table->string('provider_order_id')->unique();
            $table->string('provider_payment_id')->nullable();
            // The year it paid for (set when paid).
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
