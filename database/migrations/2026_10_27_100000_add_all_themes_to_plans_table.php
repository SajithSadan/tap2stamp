<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shops on a plan with this ticked (and paid up / in its trial) can
        // pick any theme; everyone else only the admin's free themes.
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('all_themes')->default(false)->after('trial_days');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('all_themes');
        });
    }
};
