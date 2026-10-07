<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * shops.country (ISO 3166-1 alpha-2, default GB): only UK shops can order
 * our hardware; elsewhere the owner downloads the QR codes the admin
 * assigned, in the design the admin picked (shops.qr_design_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->char('country', 2)->default('GB')->after('postcode');
            $table->foreignId('qr_design_id')->nullable()->after('country')->constrained()->nullOnDelete();
        });

        // Shops set up with an Indian number or PIN code before the country existed.
        DB::table('shops')
            ->where(fn ($q) => $q->where('contact_phone', 'like', '+91%')->orWhereRaw("postcode REGEXP '^[0-9]{6}$'"))
            ->update(['country' => 'IN']);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_design_id');
            $table->dropColumn('country');
        });
    }
};
