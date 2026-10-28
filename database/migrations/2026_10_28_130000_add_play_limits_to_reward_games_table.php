<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_games', function (Blueprint $table) {
            // max_plays_per_customer per day / week / month / total (null = total, its old meaning).
            $table->string('plays_period', 10)->nullable()->after('max_plays_per_customer');
            // After a customer wins: keep playing (continue), done for the day (day), done with this game (game).
            $table->string('after_win', 10)->default('continue')->after('plays_period');
        });
    }

    public function down(): void
    {
        Schema::table('reward_games', function (Blueprint $table) {
            $table->dropColumn(['plays_period', 'after_win']);
        });
    }
};
