<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->string('name');
            // Hashed like a password - the plain PIN is only ever typed by the
            // owner (when setting it) and the staff member (when signing in).
            $table->string('pin_hash');
            // Soft "remove": keeps their name on past stamp_logs rows.
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            // Staff pick their own name from a list on the shared device.
            $table->unique(['shop_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
