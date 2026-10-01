<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stamp_logs', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('staff_member_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stamp_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};