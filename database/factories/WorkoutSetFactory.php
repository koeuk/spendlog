<?php

namespace Database\Factories;

use App\Models\ExerciseType;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    /**
     * Defaults to a strength set, because that is the shape the volume figures
     * and the PR list are built from — a factory defaulting to cardio would
     * make every dashboard test silently compute zero.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'exercise_type_id' => ExerciseType::factory()->strength(),
            'set_no' => 1,
            'reps' => fake()->numberBetween(5, 12),
            'weight_kg' => fake()->randomFloat(3, 20, 120),
            'distance_m' => null,
            'duration_seconds' => null,
            'rpe' => fake()->boolean(40) ? fake()->numberBetween(6, 10) : null,
        ];
    }

    public function cardio(): static
    {
        return $this->state(fn () => [
            'exercise_type_id' => ExerciseType::factory()->cardio(),
            'reps' => null,
            'weight_kg' => null,
            'distance_m' => fake()->numberBetween(1000, 15000),
            'duration_seconds' => fake()->numberBetween(10 * 60, 90 * 60),
        ]);
    }

    /** Bodyweight work: reps, and nothing on the bar. */
    public function bodyweight(): static
    {
        return $this->state(fn () => ['weight_kg' => null]);
    }
}
