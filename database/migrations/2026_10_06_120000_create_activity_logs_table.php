<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit trail of what admins, owners and staff do (never customers).
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // admin | owner | staff
            $table->string('actor_type', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();
            // Copied at the time, so the log still reads right after a rename / delete.
            $table->string('actor_name', 160);
            // Set when an admin did it while viewing a shop as its owner.
            $table->boolean('as_owner')->default(false);
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            // e.g. stamp.added, shop.updated, staff_member.created
            $table->string('action', 64);
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 255);
            // {field: [before, after]} for changes, or extra detail.
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
