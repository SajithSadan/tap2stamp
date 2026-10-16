<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivered / read ticks on offers, from Meta's webhook (App\Services\WhatsAppWebhook).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            // The webhook finds a message by Meta's id.
            $table->index('wa_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_messages', function (Blueprint $table) {
            $table->dropIndex(['wa_message_id']);
            $table->dropColumn(['delivered_at', 'read_at']);
        });
    }
};
