<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google sign-in.
 *
 * google_id rather than matching on email alone: an email address is something
 * a provider reports, and matching on it makes "prove you own this address" the
 * provider's job forever. The subject id is stable, unique to the account, and
 * survives the user renaming their Gmail.
 *
 * password becomes nullable because an account that only ever arrived through
 * Google has no password to store, and a hash of nothing is worse than a null:
 * it would look like a credential that could be tried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });

        // Not reversed: rows created through Google have no password to restore,
        // so making the column NOT NULL again would fail on exactly the data
        // this migration exists to allow.
    }
};
