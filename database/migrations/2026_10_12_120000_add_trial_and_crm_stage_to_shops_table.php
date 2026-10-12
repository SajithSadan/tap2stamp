<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // Free trial: ends this long after the shop was set up (config loyalty.trial_days);
            // the admin can move it (e.g. extend an active shop). Nothing is enforced yet.
            $table->timestamp('trial_ends_at')->nullable()->after('blocked_reason');
            // Sales pipeline (App\Support\CrmStage): trial / offered / paid / lost. "Activated"
            // isn't stored - it's a trial shop that has given its first stamp.
            $table->string('crm_stage', 20)->default('trial')->after('trial_ends_at');
            $table->string('lost_reason', 255)->nullable()->after('crm_stage');
        });

        // Every existing shop: its trial from when it was set up.
        $days = (int) config('loyalty.trial_days', 30);
        DB::table('shops')->whereNull('trial_ends_at')->update([
            'trial_ends_at' => DB::raw("DATE_ADD(created_at, INTERVAL {$days} DAY)"),
        ]);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['trial_ends_at', 'crm_stage', 'lost_reason']);
        });
    }
};
