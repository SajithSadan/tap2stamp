<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the QR itself looks on a design (colours, shapes, frame, centre,
     * caption - see App\Support\QrStyle; null = the plain defaults), and an
     * optional logo for the middle of the QR, on the `uploads` disk.
     */
    public function up(): void
    {
        Schema::table('qr_designs', function (Blueprint $table) {
            $table->json('style')->nullable()->after('width_mm');
            $table->string('logo_path')->nullable()->after('style');
        });
    }

    public function down(): void
    {
        Schema::table('qr_designs', function (Blueprint $table) {
            $table->dropColumn(['style', 'logo_path']);
        });
    }
};
