<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional opt-in to promotions from this one shop. Lives on the card,
     * not the customer: consent is given to a specific business, never to
     * every shop on the platform. The timestamp is the record of when it
     * was given (UK GDPR/PECR need that proof).
     */
    public function up(): void
    {
        Schema::table('customer_shop_cards', function (Blueprint $table) {
            $table->boolean('marketing_consent')->default(false)->after('last_stamped_at');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
        });
    }

    public function down(): void
    {
        Schema::table('customer_shop_cards', function (Blueprint $table) {
            $table->dropColumn(['marketing_consent', 'marketing_consent_at']);
        });
    }
};
