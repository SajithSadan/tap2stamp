<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the providers' webhooks tell us after a payment: refunds (a full
        // one takes the paid year back) and chargebacks (flagged for the admin).
        Schema::table('subscription_payments', function (Blueprint $table) {
            // Smallest unit, like `amount`.
            $table->unsignedBigInteger('amount_refunded')->default(0)->after('amount');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->timestamp('disputed_at')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['amount_refunded', 'refunded_at', 'disputed_at']);
        });
    }
};
