<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shops created before the contact fields existed (or by an admin) have no
 * contact person/email. Fill them in from the owner's login, like sign-up does.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shops')
            ->join('users', 'users.id', '=', 'shops.user_id')
            ->where(fn ($q) => $q->whereNull('shops.contact_email')->orWhere('shops.contact_email', ''))
            ->update(['shops.contact_email' => DB::raw('users.email')]);

        DB::table('shops')
            ->join('users', 'users.id', '=', 'shops.user_id')
            ->where(fn ($q) => $q->whereNull('shops.contact_name')->orWhere('shops.contact_name', ''))
            ->update(['shops.contact_name' => DB::raw('users.name')]);
    }

    public function down(): void
    {
        // Data only - nothing to undo.
    }
};
