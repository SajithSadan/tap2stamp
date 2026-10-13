<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A browser or a whole network locked for typing too many wrong shop codes.
 * Stays locked until an admin unlocks it (App\Services\ShopCodeJoin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_code_locks', function (Blueprint $table) {
            $table->id();
            // One of the two: a browser, or a whole network (IP).
            $table->string('browser_id', 64)->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_code_locks');
    }
};
