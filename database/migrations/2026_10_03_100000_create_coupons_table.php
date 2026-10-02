<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            // Stored upper-case; owners can type it in any case.
            $table->string('code', 32)->unique();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed']);
            // percent: 1-100, fixed: pence off the order total.
            $table->unsignedInteger('discount_value');
            // null = any product.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('once_per_shop')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('list_total_pence')->constrained()->nullOnDelete();
            // Copied at order time, like product_name.
            $table->string('coupon_code', 32)->nullable()->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });

        Schema::dropIfExists('coupons');
    }
};
