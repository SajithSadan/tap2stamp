<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shop's reward games (App\Support\RewardGames): built by the owner, published when ready.
        Schema::create('reward_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('type', 20);
            $table->string('status', 10)->default('draft');
            // When a customer gets a play: every Nth stamp (trigger_value = N), a full card, the first stamp.
            $table->string('trigger', 20);
            $table->unsignedSmallInteger('trigger_value')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('max_plays_per_customer')->nullable();
            $table->unsignedSmallInteger('voucher_days')->default(30);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });

        Schema::create('reward_game_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_game_id')->constrained()->cascadeOnDelete();
            $table->string('title', 60);
            $table->string('detail', 120)->nullable();
            // item / discount (a voucher staff give out), bonus_stamps, no_win.
            $table->string('kind', 20);
            $table->unsignedTinyInteger('bonus_stamps')->nullable();
            // Chance relative to the game's other prizes.
            $table->unsignedSmallInteger('weight');
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('won_count')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_game_prizes');
        Schema::dropIfExists('reward_games');
    }
};
