<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_designs', function (Blueprint $table) {
            // The design overseas owners download their QR in when their shop has none of its own. At most one.
            $table->boolean('is_default')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('qr_designs', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
