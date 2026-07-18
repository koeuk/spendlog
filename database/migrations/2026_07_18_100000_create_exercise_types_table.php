<?php

use App\Enums\CategoryColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            /*
             * Nullable on purpose, and this is the column that makes the table
             * work: NULL is a seeded global movement everyone sees (Bench Press,
             * Running), a set user_id is one that person invented.
             *
             * Note this is the opposite of categories, which are global for
             * everyone with no user_id at all. Spending taxonomy is shared
             * because two people's "Food" is the same bucket; a lift is not —
             * people invent their own, and one person's accessory work has no
             * business appearing in someone else's picker.
             */
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Translatable JSON — {"en": "Bench Press", "km": "…"} — like categories.name.
            // Resolved and searched per-locale via App\Support\TranslatableQuery.
            $table->json('name');
            $table->string('muscle_group', 20);
            /*
             * Decides which half of workout_sets a row fills in: a cardio type
             * logs distance and duration, a strength one logs reps and weight.
             * Stored on the type rather than inferred from muscle_group so a
             * bodyweight circuit filed under FullBody can still be timed.
             */
            $table->boolean('is_cardio')->default(false);
            $table->string('color', 20)->default(CategoryColor::Slate->value);
            $table->string('icon', 40)->nullable();
            $table->timestamps();

            // Drives the picker, which loads the caller's own types plus the globals.
            $table->index('user_id');

            /*
             * No unique index on name: MySQL cannot index a JSON column directly,
             * so per-locale uniqueness is enforced in ExerciseTypeRequest instead
             * — scoped to the owner, so two people may both name a lift "Row".
             * Same trade-off as categories.
             */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_types');
    }
};
