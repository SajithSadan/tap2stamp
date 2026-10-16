<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shop's WhatsApp chats (App\Services\WhatsAppInbox): customers' messages
 * from Meta's webhook (in) and the owner's replies (out).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            // The customer's card at this shop, when the number is one of theirs.
            $table->foreignId('customer_shop_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            // The sender's WhatsApp profile name (incoming only).
            $table->string('name', 150)->nullable();
            $table->string('direction', 3);
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            // Meta's id; unique, so a webhook Meta sends twice is stored once.
            $table->string('wa_message_id')->nullable()->unique();
            // Replies: sent → delivered → read, or failed.
            $table->string('status', 16)->nullable();
            $table->string('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // When the owner opened the chat (incoming only).
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
