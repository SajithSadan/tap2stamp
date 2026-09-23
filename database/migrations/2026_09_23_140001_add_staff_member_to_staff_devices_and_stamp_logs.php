<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who is currently signed in on this (owner-approved) device, and
        // since when - the sign-in expires after loyalty.staff_session_hours.
        Schema::table('staff_devices', function (Blueprint $table) {
            $table->foreignId('staff_member_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->timestamp('staff_signed_in_at')->nullable()->after('staff_member_id');
        });

        // Nullable: stamps logged before staff accounts existed have no one
        // to attribute them to.
        Schema::table('stamp_logs', function (Blueprint $table) {
            $table->foreignId('staff_member_id')->nullable()->after('shop_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stamp_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_member_id');
        });

        Schema::table('staff_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_member_id');
            $table->dropColumn('staff_signed_in_at');
        });
    }
};
