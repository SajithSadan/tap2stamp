<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // The public menu link (/menu/{menu_slug}): the name + a short code,
            // never the card slug, so the menu doesn't give owners their card link.
            $table->string('menu_slug', 80)->nullable()->unique()->after('menu_theme');
        });

        // Existing shops (inline, not via the model, so later model changes can't break this).
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        DB::table('shops')->whereNull('menu_slug')->orderBy('id')->each(function ($shop) use ($alphabet) {
            $base = Str::limit(Str::slug($shop->name), 60, '') ?: 'shop';
            do {
                $code = '';
                for ($i = 0; $i < 4; $i++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $slug = "{$base}-{$code}";
            } while (DB::table('shops')->where('menu_slug', $slug)->exists());

            DB::table('shops')->where('id', $shop->id)->update(['menu_slug' => $slug]);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique(['menu_slug']);
            $table->dropColumn('menu_slug');
        });
    }
};
