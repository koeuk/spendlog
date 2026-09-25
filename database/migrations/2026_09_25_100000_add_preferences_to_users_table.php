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
             * One account's own choices, each overriding the app-wide value an
             * admin set under Settings. Null means "no choice of my own": the
             * account follows the admin, and keeps following when the admin
             * changes it, rather than being frozen at whatever it copied.
             *
             * Entry and looks only. The rate stays the admin's, since every
             * stored amount is converted at it.
             */
            $table->string('preferred_currency', 3)->nullable()->after('status');
            $table->string('button_color', 7)->nullable()->after('preferred_currency');
            $table->string('body_color', 7)->nullable()->after('button_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['preferred_currency', 'button_color', 'body_color']);
        });
    }
};
