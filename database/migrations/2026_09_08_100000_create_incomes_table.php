<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The rule that wrote this row, when one did. Nulling on delete so
            // removing a rule keeps the history it wrote.
            $table->foreignId('recurring_rule_id')->nullable()->constrained()->nullOnDelete();
            // Free text — "Salary", "Freelance", "Gift". Deliberately not a
            // shared catalogue like categories: where money comes from is
            // personal, and the summary groups on the string as typed.
            $table->string('source');
            // Four decimal places for the same reason as expenses.price: an
            // amount can be entered in riel, and cent precision is coarser
            // than the amounts being entered. Always stored in USD.
            $table->decimal('amount', 12, 4);
            $table->date('received_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // Drives the date-sorted list and the monthly summary.
            $table->index(['user_id', 'received_on']);
            // What makes a recurring run idempotent — one row per rule per day,
            // whatever else happens.
            $table->unique(['recurring_rule_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
