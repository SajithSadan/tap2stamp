<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_shop_cards', function (Blueprint $table) {
            // The unsubscribe link's secret (/u/{token}), made on the first message sent.
            $table->string('marketing_unsubscribe_token', 32)->nullable()->unique()->after('marketing_consent_at');
            $table->timestamp('marketing_opted_out_at')->nullable()->after('marketing_unsubscribe_token');
        });

        // One WhatsApp send from a shop to its opted-in customers.
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 16);
            $table->string('message', 500);
            $table->unsignedInteger('recipients_count');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            // sending → sent (every message tried)
            $table->string('status', 16)->default('sending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
        });

        // One row per recipient, so sending can go in small batches (no queue workers).
        Schema::create('marketing_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_shop_card_id')->constrained()->cascadeOnDelete();
            // pending → sending → sent | failed
            $table->string('status', 16)->default('pending');
            $table->string('wa_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['marketing_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_messages');
        Schema::dropIfExists('marketing_campaigns');

        Schema::table('customer_shop_cards', function (Blueprint $table) {
            $table->dropUnique(['marketing_unsubscribe_token']);
            $table->dropColumn(['marketing_unsubscribe_token', 'marketing_opted_out_at']);
        });
    }
};
