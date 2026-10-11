<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Does the owner find and download their QR? Each download is also in activity_logs.
            $table->timestamp('qr_page_viewed_at')->nullable()->after('qr_design_id');
            $table->timestamp('qr_downloaded_at')->nullable()->after('qr_page_viewed_at');
            $table->unsignedInteger('qr_downloads')->default(0)->after('qr_downloaded_at');
            $table->unsignedInteger('qr_download_failures')->default(0)->after('qr_downloads');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['qr_page_viewed_at', 'qr_downloaded_at', 'qr_downloads', 'qr_download_failures']);
        });
    }
};
