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
            $table->decimal('price', 10, 2);
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
