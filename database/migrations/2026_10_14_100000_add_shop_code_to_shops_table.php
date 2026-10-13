<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every shop's own unique code: a staff phone that isn't set up joins its
 * shop by typing it (App\Services\ShopCodeJoin).
 */
return new class extends Migration
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('shop_code', 8)->nullable()->unique()->after('menu_slug');
        });

        // Every existing shop gets one (new shops get theirs in Shop::booted()).
        $taken = [];
        DB::table('shops')->whereNull('shop_code')->orderBy('id')->pluck('id')->each(function (int $id) use (&$taken) {
            do {
                $code = '';
                for ($i = 0; $i < 6; $i++) {
                    $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
            } while (isset($taken[$code]));
            $taken[$code] = true;
            DB::table('shops')->where('id', $id)->update(['shop_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique(['shop_code']);
            $table->dropColumn('shop_code');
        });
    }
};
