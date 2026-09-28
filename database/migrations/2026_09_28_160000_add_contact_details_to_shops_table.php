<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Business contact + location, collected at shop setup (see
        // App\Support\ShopContact). Nullable in the table because shops made
        // before this existed don't have them; the forms require them.
        Schema::table('shops', function (Blueprint $table) {
            $table->string('contact_name', 150)->nullable()->after('reward_title');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 20)->nullable()->after('contact_email');
            $table->string('address_line1', 150)->nullable()->after('contact_phone');
            $table->string('address_line2', 150)->nullable()->after('address_line1');
            $table->string('town', 100)->nullable()->after('address_line2');
            $table->string('postcode', 12)->nullable()->after('town');
            // Null = send post to the shop address above.
            $table->text('delivery_address')->nullable()->after('postcode');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name', 'contact_email', 'contact_phone',
                'address_line1', 'address_line2', 'town', 'postcode', 'delivery_address',
            ]);
        });
    }
};
