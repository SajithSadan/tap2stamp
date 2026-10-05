<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // App\Support\MenuThemes key; null = MenuThemes::DEFAULT.
            $table->string('menu_theme', 32)->nullable()->after('show_card_link');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('menu_theme');
        });
    }
};
