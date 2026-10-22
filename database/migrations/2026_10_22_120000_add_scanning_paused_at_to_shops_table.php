<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set by the admin (shop settings → Account → Pause scanning), never
        // automatically: while set, the shop's staff and owner scanners can't stamp.
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('scanning_paused_at')->nullable()->after('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('scanning_paused_at');
        });
    }
};
