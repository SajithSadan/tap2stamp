<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our offer templates on each shop's own WhatsApp account
 * (App\Services\WhatsAppTemplates): Meta's review status per template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_whatsapp_accounts', function (Blueprint $table) {
            // {template name: Meta's status (APPROVED, PENDING, REJECTED…) or NOT_CREATED}.
            $table->json('templates')->nullable()->after('access_token');
            $table->timestamp('templates_checked_at')->nullable()->after('templates');
        });
    }

    public function down(): void
    {
        Schema::table('shop_whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn(['templates', 'templates_checked_at']);
        });
    }
};
