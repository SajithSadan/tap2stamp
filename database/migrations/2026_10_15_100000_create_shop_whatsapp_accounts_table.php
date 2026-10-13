<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shop's own WhatsApp Business number, connected by the owner (Meta's
 * Embedded Signup) or by the admin with keys. WhatsApp offers to the shop's
 * customers go out from it (App\Services\WhatsAppConnect). One per shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            // new = created in Meta's popup, existing = the WhatsApp Business app
            // number (coexistence), keys = pasted by the admin.
            $table->string('mode', 16);
            $table->string('waba_id', 64);
            $table->string('phone_number_id', 64);
            $table->string('display_phone_number', 32)->nullable();
            $table->string('verified_name', 150)->nullable();
            // Encrypted by the model (APP_KEY); never sent to the browser.
            $table->text('access_token');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_whatsapp_accounts');
    }
};
