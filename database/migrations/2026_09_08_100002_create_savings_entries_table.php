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
            // Denormalised from the goal so "everything this person saved this
            // month" is one indexed read rather than a join through goals.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            /*
             * Signed: positive is a deposit, negative a withdrawal.
             *
             * One column rather than a type flag, so a goal's balance is a plain
             * SUM(amount) and there is no way to add a withdrawal that the sum
             * forgets to subtract. The API still speaks in "deposit"/"withdraw"
             * with an absolute amount — see SavingsEntryResource.
             */
            $table->decimal('amount', 12, 4);
            $table->date('saved_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // The goal's ledger, newest first.
            $table->index(['savings_goal_id', 'saved_on']);
            // The month's deposits across every goal, for the summary.
            $table->index(['user_id', 'saved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_entries');
    }
};
