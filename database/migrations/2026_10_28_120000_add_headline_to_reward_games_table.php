<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What customers read above the game ("Spin to win!"). Null = the game type's own line.
        Schema::table('reward_games', function (Blueprint $table) {
            $table->string('headline', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('reward_games', function (Blueprint $table) {
            $table->dropColumn('headline');
        });
    }
};
