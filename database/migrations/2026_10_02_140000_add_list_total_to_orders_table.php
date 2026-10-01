<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The normal price at order time; above total_pence = a discount
            // the admin agreed (promotion, free display).
            $table->unsignedInteger('list_total_pence')->nullable()->after('total_pence');
        });

        DB::table('orders')->update(['list_total_pence' => DB::raw('total_pence')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('list_total_pence');
        });
    }
};
