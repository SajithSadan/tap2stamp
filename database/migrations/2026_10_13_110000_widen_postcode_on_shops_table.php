<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The column was sized for UK postcodes (12), but countries without a
     * known format accept up to 20 characters (ShopContact::rules), e.g. a
     * plus code "79XF+RG SHARJAH" - which failed the insert after passing
     * validation.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('postcode', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('postcode', 12)->nullable()->change();
        });
    }
};
