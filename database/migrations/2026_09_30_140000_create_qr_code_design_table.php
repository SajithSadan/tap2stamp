<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qr_code_design', function (Blueprint $table) {
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_design_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['qr_code_id', 'qr_design_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_code_design');
    }
};