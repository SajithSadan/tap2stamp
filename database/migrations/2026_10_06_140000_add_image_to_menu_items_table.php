<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // On the `uploads` disk (menu-items/{shop}/…): fetched from the product
            // catalog and checked by Gemini first (App\Services\MenuItemImages).
            $table->string('image_path')->nullable()->after('tags');
            // null = not looked for yet | found | not_found | rejected (AI said no) | removed (taken off by hand)
            $table->string('image_status', 16)->nullable()->after('image_path');
            $table->decimal('image_confidence', 4, 3)->nullable()->after('image_status');
            $table->string('image_reason', 500)->nullable()->after('image_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_status', 'image_confidence', 'image_reason']);
        });
    }
};
