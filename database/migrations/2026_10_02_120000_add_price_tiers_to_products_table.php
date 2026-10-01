<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Price breaks: [{from: 2, price_pence: 2000}, ...] - items from that
            // position on cost that much each. Null = price_pence for every item.
            $table->json('price_tiers')->nullable()->after('price_pence');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_tiers');
        });
    }
};
