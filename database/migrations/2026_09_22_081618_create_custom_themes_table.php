<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('base_theme_slug');

            $table->string('page_bg', 7);
            $table->string('card_bg', 7);
            $table->string('text', 7);
            $table->string('muted', 7);
            $table->string('accent', 7);
            $table->string('accent_text', 7);
            $table->string('stamp_filled', 7);
            $table->string('stamp_empty', 7);
            $table->string('border', 7);

            $table->string('heading_font_name');
            $table->string('heading_font_fallback');
            $table->string('body_font_name');
            $table->string('body_font_fallback');

            $table->string('radius', 10);
            $table->string('button_radius', 10);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_themes');
    }
};
