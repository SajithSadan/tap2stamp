<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Off: the owner's dashboard never shows their card link or a
            // printable QR of it (customers come in through our counter
            // display). The admin can switch it on per shop.
            $table->boolean('show_card_link')->default(false)->after('google_review_direct');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('show_card_link');
        });
    }
};
