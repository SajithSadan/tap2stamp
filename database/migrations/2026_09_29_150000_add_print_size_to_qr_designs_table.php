<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exact printed size of a design: a named preset (stand, table - see
     * QrDesign::PRESETS) or custom, and an explicit height (null = follow
     * the artwork's shape at width_mm, as before).
     */
    public function up(): void
    {
        Schema::table('qr_designs', function (Blueprint $table) {
            $table->string('preset', 20)->nullable()->after('width_mm');
            $table->unsignedSmallInteger('height_mm')->nullable()->after('preset');
        });
    }

    public function down(): void
    {
        Schema::table('qr_designs', function (Blueprint $table) {
            $table->dropColumn(['preset', 'height_mm']);
        });
    }
};
