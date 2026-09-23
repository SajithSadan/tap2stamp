<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Owner opt-in: also show their chosen theme in their own dashboard.
            $table->boolean('theme_in_dashboard')->default(false)->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('theme_in_dashboard');
        });
    }
};
