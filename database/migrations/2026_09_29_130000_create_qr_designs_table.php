<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sticker designs for printed QR codes: a background image with the spot
     * where each code's QR goes. The position is stored as fractions of the
     * image (0-1), so it doesn't depend on the size it's shown or printed at.
     */
    public function up(): void
    {
        Schema::create('qr_designs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('image_path');
            $table->unsignedInteger('image_width');
            $table->unsignedInteger('image_height');
            // QR's top-left corner and side length. x and size are fractions
            // of the image width, y of the image height (the QR is square).
            $table->decimal('qr_x', 6, 5);
            $table->decimal('qr_y', 6, 5);
            $table->decimal('qr_size', 6, 5);
            // Printed width of one sticker; its height follows the image's shape.
            $table->unsignedSmallInteger('width_mm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_designs');
    }
};
