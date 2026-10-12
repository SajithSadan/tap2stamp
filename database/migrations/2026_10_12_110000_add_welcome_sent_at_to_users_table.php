<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the admin first sent this owner the WhatsApp welcome (each send is in activity_logs).
            $table->timestamp('welcome_sent_at')->nullable()->after('onboarding_draft');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcome_sent_at');
        });
    }
};
