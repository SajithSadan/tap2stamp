<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // What kind of business (App\Support\Industries); null = not asked yet (older shops).
            $table->string('industry', 40)->nullable()->after('name');
            // The owner's own words when they picked "Something else".
            $table->string('industry_other', 80)->nullable()->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['industry', 'industry_other']);
        });
    }
};
