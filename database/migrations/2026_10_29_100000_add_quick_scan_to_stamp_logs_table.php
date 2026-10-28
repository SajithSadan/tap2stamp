<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A stamp the customer took themselves with Quick scan (no staff member, no owner). */
    public function up(): void
    {
        Schema::table('stamp_logs', function (Blueprint $table) {
            $table->boolean('quick_scan')->default(false)->after('action_type');
        });
    }

    public function down(): void
    {
        Schema::table('stamp_logs', function (Blueprint $table) {
            $table->dropColumn('quick_scan');
        });
    }
};
