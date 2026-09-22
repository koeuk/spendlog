<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Which table the rule writes to: "expense" or "income". A rule is
            // a template, not a row — the money lives on what it creates.
            $table->string('kind', 16);
            // Required for an expense rule, null for an income one. Restricting
            // rather than nulling: a category that still drives a rule is in
            // use, the same as one that still holds expenses.
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            // The expense item or the income source, as typed.
            $table->string('title');
            // Four decimal places for the same reason as expenses.price. Always
            // stored in USD.
            $table->decimal('amount', 12, 4);
            $table->string('frequency', 16);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // The next occurrence still to be created. A run walks this forward
            // one step per row it writes, so it is also the rule's cursor.
            $table->date('next_run_on');
            $table->date('last_run_on')->nullable();
            $table->boolean('active')->default(true);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // The "what is due" read on every dashboard call and the nightly run.
            $table->index(['user_id', 'active', 'next_run_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
    }
};
