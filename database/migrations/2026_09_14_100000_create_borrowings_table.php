<?php

use App\Enums\LenderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Who lent it — free text ("Mom", "Sokha", "ABA Bank"), personal
            // like an income source. The type beside it is what the list
            // groups and filters on.
            $table->string('lender');
            $table->string('lender_type', 20)->default(LenderType::Other->value);
            // What was borrowed. Four places, like every other money column —
            // see expenses.price. What is still owed is never stored: it is
            // always amount − SUM(repayments), so it cannot drift.
            $table->decimal('amount', 12, 4);
            $table->date('borrowed_on');
            // Optional: a loan from a friend rarely has one.
            $table->date('due_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'borrowed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};
