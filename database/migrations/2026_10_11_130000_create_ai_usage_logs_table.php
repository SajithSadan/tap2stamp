<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Gemini request (menu photo import, photo check, stock-out command): who, which shop, tokens.
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 30);
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 10)->nullable(); // admin | owner | staff, null = no one signed in
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable(); // snapshot, like activity_logs
            $table->string('model', 60)->nullable();
            $table->string('status', 15); // ok | busy | failed | unreachable
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
