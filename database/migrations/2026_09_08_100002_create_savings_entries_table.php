<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /*
             * Signed: positive is a deposit, negative a withdrawal.
             *
             * One column rather than a type flag, so the balance is a plain
             * SUM(amount) and there is no way to add a withdrawal that the sum
             * forgets to subtract. The API still speaks in "deposit"/"withdraw"
             * with an absolute amount — see SavingsEntryResource.
             */
            $table->decimal('amount', 12, 4);
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
            $table->string('source')->nullable();
            $table->date('saved_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // The month's deposits, for the summary, and the ledger itself.
            $table->index(['user_id', 'saved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_entries');
    }
};
