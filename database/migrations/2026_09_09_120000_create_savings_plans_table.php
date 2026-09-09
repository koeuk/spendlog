<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much a person intends to put aside in one month.
     *
     * Deliberately the same shape as `budgets`: one amount per (user, month),
     * upserted rather than created and edited, so the two "here is my number
     * for this month" features behave identically. There is no target and no
     * end date — what was actually saved is the sum of savings_entries.
     */
    public function up(): void
    {
        Schema::create('savings_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Always stored as the first day of the month it applies to, so a
            // month collides on the unique index below however it was entered.
            $table->date('month');
            // Four decimal places for the same reason as budgets.amount: a plan
            // can be entered in riel, and cent precision is coarser than the
            // amounts being entered. See App\Enums\Currency.
            $table->decimal('amount', 12, 4);
            $table->timestamps();

            // One plan per month. No generated column is needed here — unlike
            // budgets there is no nullable category in the key.
            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_plans');
    }
};
