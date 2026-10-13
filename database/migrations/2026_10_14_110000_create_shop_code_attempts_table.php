<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every shop code typed on a staff phone that isn't set up, right or wrong.
 * Wrong ones are counted per day to lock a browser or network (App\Services\ShopCodeJoin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_code_attempts', function (Blueprint $table) {
            $table->id();
            // A random id the browser keeps (localStorage), and its IP address.
            $table->string('browser_id', 64);
            $table->string('ip', 45);
            $table->boolean('success');
            $table->timestamps();

            $table->index(['browser_id', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_code_attempts');
    }
};
