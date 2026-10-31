<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coupon codes for plan payments - separate from the counter display's `coupons`.
        Schema::create('plan_coupons', function (Blueprint $table) {
            $table->id();
            // Stored upper-case; owners can type it in any case.
            $table->string('code', 32)->unique();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed']);
            // {country (alpha-2): discount as typed} - percent 1-100, or an amount in
            // that country's currency. Only these countries can use it.
            $table->json('discounts');
            // null = any plan.
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('once_per_shop')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('plan_coupon_id')->nullable()->after('amount')->constrained()->nullOnDelete();
            // Copied at payment time, like plan_name.
            $table->string('coupon_code', 32)->nullable()->after('plan_coupon_id');
            // The plan's price before the coupon (smallest unit); null = no coupon.
            $table->unsignedBigInteger('list_amount')->nullable()->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_coupon_id');
            $table->dropColumn(['coupon_code', 'list_amount']);
        });

        Schema::dropIfExists('plan_coupons');
    }
};
