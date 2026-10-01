<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Price of one item at order time (total = unit × quantity).
            $table->unsignedInteger('unit_price_pence')->default(0)->after('quantity');
            // Fulfilment: received (= paid_at) → processing → dispatched → delivered.
            $table->timestamp('processing_at')->nullable()->after('paid_at');
            $table->timestamp('delivered_at')->nullable()->after('dispatched_at');
            // Filled in by the admin when posting it; shown to the owner.
            $table->string('courier', 60)->nullable()->after('delivered_at');
            $table->string('tracking_number', 100)->nullable()->after('courier');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
        });

        DB::table('orders')->update(['unit_price_pence' => DB::raw('total_pence DIV GREATEST(quantity, 1)')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['unit_price_pence', 'processing_at', 'delivered_at', 'courier', 'tracking_number', 'tracking_url']);
        });
    }
};
