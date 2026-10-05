<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One menu per shop: sections (Coffee, Pastries…) holding items.
        Schema::create('menu_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_section_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            // As printed on the menu ("£3.20", "Reg £3.20 · Lg £3.80") - display only.
            $table->string('price', 40)->nullable();
            // Free-text labels ("Vegan", "Halal", "New"…), see MenuItem::tidyTags().
            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_sections');
    }
};
