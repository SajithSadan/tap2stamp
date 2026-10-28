<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The scratch card's own image (what customers scratch off), on the uploads disk. Null = plain silver.
        Schema::table('reward_games', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('reward_games', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
