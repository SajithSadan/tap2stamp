<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Set by the admin (e.g. shop closing, subscription unpaid); null = active.
            $table->timestamp('blocked_at')->nullable()->after('features');
            // Admin-only note on why - never shown to the owner, staff or customers.
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
