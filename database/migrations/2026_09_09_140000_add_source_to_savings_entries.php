<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_entries', function (Blueprint $table) {
            /*
             * Where a deposit came from — "Salary", "Freelance" — offered from
             * the sources the account already uses on its income.
             *
             * A label, deliberately, not a foreign key to an income row. The
             * money put aside in September is rarely one payment: it is a bit
             * of this month's salary and what was left of last month's. Naming
             * a single income as its origin would be a precision the person
             * entering it does not have, and would then have to be maintained
             * when that income is edited or deleted.
             */
            $table->string('source')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('savings_entries', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
