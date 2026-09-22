<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_shop_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->unsignedTinyInteger('current_stamps')->default(0);
            $table->unsignedInteger('rewards_claimed')->default(0);
            $table->timestamp('last_stamped_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'shop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_shop_cards');
    }
};
