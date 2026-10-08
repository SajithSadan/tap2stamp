<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Per-shop feature overrides ({"menu": true, "whatsapp": false});
            // a missing key = the platform default (App\Support\Features).
            $table->json('features')->nullable()->after('show_card_link');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
