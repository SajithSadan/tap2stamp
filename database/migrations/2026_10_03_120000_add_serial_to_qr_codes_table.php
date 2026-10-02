<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            // The code's number in its batch (001, 002 …), fixed at generation.
            // It used to be counted on the fly, which would renumber every later
            // code once single codes can be deleted - not what's on the stickers.
            $table->unsignedInteger('serial')->default(0)->after('code');
        });

        // Same numbers as before: the position in its batch, in generation order.
        DB::statement('UPDATE qr_codes q JOIN (
            SELECT id, ROW_NUMBER() OVER (PARTITION BY qr_batch_id ORDER BY id) AS pos FROM qr_codes
        ) numbered ON numbered.id = q.id SET q.serial = numbered.pos');
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn('serial');
        });
    }
};
