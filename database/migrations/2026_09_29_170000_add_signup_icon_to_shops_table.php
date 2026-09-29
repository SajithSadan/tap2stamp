<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The small decorative icon on the customer sign-up screen (between the
     * form and the footer), picked by the owner so it fits the business.
     * Keys: App\Support\SignupIcons::KEYS. Null = the neutral default.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('signup_icon', 30)->nullable()->after('stamp_icon');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('signup_icon');
        });
    }
};
