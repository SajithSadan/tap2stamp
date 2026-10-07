<?php

use App\Support\Countries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shop's contact number in two parts: shops.contact_phone_code (the
 * dialling code, digits only, e.g. "44") and shops.contact_phone (the
 * number without it, e.g. "7700900123"). Shown together as "+44 7700900123".
 * Existing "+447700900123" numbers are split here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('contact_phone_code', 4)->nullable()->after('contact_email');
        });

        // Longest code first, so +971 isn't read as +9 and +44 as +4.
        $codes = collect(Countries::ALL)->pluck(1)->unique()->sortByDesc(fn ($c) => strlen($c))->values();

        DB::table('shops')->where('contact_phone', 'like', '+%')->get(['id', 'contact_phone', 'country'])
            ->each(function ($shop) use ($codes) {
                $digits = substr($shop->contact_phone, 1);
                $own = Countries::dialCode($shop->country);
                $code = $own && str_starts_with($digits, $own) ? $own : $codes->first(fn ($c) => str_starts_with($digits, $c));

                if ($code) {
                    DB::table('shops')->where('id', $shop->id)->update([
                        'contact_phone_code' => $code,
                        'contact_phone' => substr($digits, strlen($code)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('shops')->whereNotNull('contact_phone_code')->whereNotNull('contact_phone')
            ->update(['contact_phone' => DB::raw("CONCAT('+', contact_phone_code, contact_phone)")]);

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('contact_phone_code');
        });
    }
};
