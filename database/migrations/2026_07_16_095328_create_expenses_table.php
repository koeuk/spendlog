<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            // Translatable JSON — {"en": "Lunch", "km": "…"} — like categories.name.
            // Resolved and searched per-locale via App\Support\TranslatableQuery.
            $table->json('item');
            /*
             * Four decimal places, not two.
             *
             * Every price is stored in USD, but many are *entered* in riel, and
             * one US cent is worth ~41៛ — so at cent precision a stored amount
             * can only land on a multiple of 41៛, and ៛100 rounds to $0.02 and
             * reads back as ៛82. Four places put the floor at ~0.4៛, below the
             * smallest note in circulation. Display still formats to cents.
             */
            $table->decimal('price', 12, 4);
            $table->date('spent_on');
            $table->timestamps();

            // Drives the daily-grouped list and the dashboard's per-period totals.
            $table->index(['user_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
