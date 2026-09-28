<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per "Generate" click, so a printed sheet can be traced back
        // to the run it came from. The batch number is simply its id.
        Schema::create('qr_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_batch_id')->constrained();
            // Printed on the sticker and baked into its /qr/{code} link, so it
            // never changes. Only destination_url is ever edited.
            $table->string('code', 16)->unique();
            $table->string('destination_url', 2048)->nullable();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('qr_batches');
    }
};
