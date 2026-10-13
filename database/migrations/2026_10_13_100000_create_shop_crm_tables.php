<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's light CRM (MONETISATION-PLAN.md phase 2): notes and follow-up
 * tasks per shop, and free-text tags on the shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('shop_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->date('due_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('shops', function (Blueprint $table) {
            // e.g. ["owner not techy", "price objection"] - App\Models\Shop::tidyCrmTags().
            $table->json('crm_tags')->nullable()->after('lost_reason');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('crm_tags');
        });
        Schema::dropIfExists('shop_tasks');
        Schema::dropIfExists('shop_notes');
    }
};
