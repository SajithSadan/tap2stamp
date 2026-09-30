<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Card page header text colour, banner tint and title shadow (App\Support\HeaderStyle). Null = defaults.
            $table->json('header_style')->nullable()->after('theme_custom');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('header_style');
        });
    }
};
