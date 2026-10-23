<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's follow-up status per shop (hot / warm / cold,
 * App\Support\LeadStatus), and the status each note was written with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('lead_status', 10)->nullable()->after('crm_tags');
        });

        Schema::table('shop_notes', function (Blueprint $table) {
            $table->string('lead_status', 10)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('shop_notes', function (Blueprint $table) {
            $table->dropColumn('lead_status');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('lead_status');
        });
    }
};
