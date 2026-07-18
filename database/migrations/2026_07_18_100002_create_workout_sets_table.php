<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            /*
             * Restrict rather than cascade, matching expenses.category_id: a
             * movement that has been performed is history, and deleting it would
             * silently rewrite past sessions. ExerciseTypeController turns the
             * resulting integrity error into a 409 rather than a 500.
             */
            $table->foreignId('exercise_type_id')->constrained()->restrictOnDelete();
            // 1-based position within the exercise, so "set 3 of 5" survives a reorder.
            $table->unsignedSmallInteger('set_no');

            /*
             * Four nullable measures, filled in pairs by exercise_types.is_cardio:
             * a strength set has reps + weight_kg, a cardio one has distance_m +
             * duration_seconds. One table rather than two because a set is a set —
             * splitting them would double every query the dashboard makes for no
             * gain, and bodyweight work legitimately fills reps with no weight.
             */
            $table->unsignedSmallInteger('reps')->nullable();
            /*
             * Three decimal places, and always kilograms.
             *
             * Every weight is stored in kg but many are *entered* in pounds, and
             * one pound is 0.45359237 kg — so at two places a 45 lb plate stores
             * as 20.41 kg and reads back as 45.00 lb only by luck. Three places
             * put the floor at one gram. See App\Enums\WeightUnit, which mirrors
             * how Currency handles the same problem for money.
             */
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // Rate of perceived exertion, 1–10. Optional, and purely a note to self.
            $table->unsignedTinyInteger('rpe')->nullable();

            $table->timestamps();

            // Drives loading a session's sets, and the per-lift progression chart.
            $table->index(['workout_id', 'set_no']);
            $table->index('exercise_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
