<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * User now implements MustVerifyEmail. Accounts that an admin had already activated
 * before that change are trusted as verified, so they are not locked out; new
 * registrations and email changes must confirm the address.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->where('status', 'active')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Nothing to undo: verification timestamps are not removed.
     */
    public function down(): void {}
};
