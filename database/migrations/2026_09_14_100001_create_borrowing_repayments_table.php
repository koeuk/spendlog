<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowing_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Denormalised from the borrowing so "everything this person paid
            // back this month" is one indexed read rather than a join.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrowing_id')->constrained()->cascadeOnDelete();
            // Always positive: a repayment only ever reduces what is owed.
            // Money going the other way is a new borrowing, not a negative
            // line here.
            $table->decimal('amount', 12, 4);
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // The borrowing's ledger, newest first.
            $table->index(['borrowing_id', 'paid_on']);
            $table->index(['user_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowing_repayments');
    }
};
