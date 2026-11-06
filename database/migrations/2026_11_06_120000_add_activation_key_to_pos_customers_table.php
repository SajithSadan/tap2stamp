<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The activation key: one-time, made when a restaurant registers (or by
        // the admin, Admin → POS customers → New key). We read it out to the
        // customer on the phone; the billing app sends it to /api/pos/activate
        // to unlock that PC. It never appears in an API response.
        Schema::table('pos_customers', function (Blueprint $table) {
            // Encrypted with APP_KEY (like the password): the admin reads it back.
            $table->text('activation_key')->nullable()->after('last_login_at');
            $table->timestamp('activation_key_expires_at')->nullable()->after('activation_key');
            // Set once the key is used: it never works on another PC after that.
            $table->timestamp('activation_key_used_at')->nullable()->after('activation_key_expires_at');
            // Wrong keys in a row; at MAX_ACTIVATION_ATTEMPTS the key is locked
            // until the admin makes a new one.
            $table->unsignedSmallInteger('activation_attempts')->default(0)->after('activation_key_used_at');
            // The PC the billing app is activated on (an id the app derives from
            // the machine), and when. Only this PC may use the app.
            $table->string('activated_device_id', 128)->nullable()->after('activation_attempts');
            $table->timestamp('activated_at')->nullable()->after('activated_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_customers', function (Blueprint $table) {
            $table->dropColumn([
                'activation_key',
                'activation_key_expires_at',
                'activation_key_used_at',
                'activation_attempts',
                'activated_device_id',
                'activated_at',
            ]);
        });
    }
};
