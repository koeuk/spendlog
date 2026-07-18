<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * A display handle, not a credential — signing in is still email and
             * password. Nullable so every existing account stays valid without a
             * backfill, and unique so the ones that are set stay distinct.
             *
             * Unique *and* nullable is the combination that does what is wanted
             * here: SQL treats NULLs as distinct from one another, so any number
             * of accounts may have no username while no two may share one.
             *
             * Uniqueness is case-insensitive for free — the column inherits the
             * table's utf8mb4_unicode_ci collation, so "Koeuk" and "koeuk" collide
             * rather than becoming two accounts nobody can tell apart.
             */
            $table->string('username', 30)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Named explicitly: dropping a column that carries a unique index
            // leaves the index behind on some MySQL versions.
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
