<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // The owner's own tweaks (colours, fonts, corners) on top of
            // shops.theme. Null = the catalog theme as-is.
            $table->json('theme_custom')->nullable()->after('theme');
            // A StampIcons key. Null = the default tick.
            $table->string('stamp_icon')->nullable()->after('theme_in_dashboard');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['theme_custom', 'stamp_icon']);
        });
    }
};
