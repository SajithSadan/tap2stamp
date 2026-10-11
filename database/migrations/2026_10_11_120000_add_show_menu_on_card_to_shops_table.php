<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // "View menu" on the customer card page (only ever shown with Menu on and items to show).
            $table->boolean('show_menu_on_card')->default(true)->after('menu_theme');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('show_menu_on_card');
        });
    }
};
