<?php

namespace Database\Factories;

use App\Enums\ExerciseIcon;
use App\Enums\MuscleGroup;
use App\Models\ExerciseType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseType>
 */
class ExerciseTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(MuscleGroup::cases());

        return [
            // Owned by default. The global catalogue is the special case, so it
            // is the named state below rather than the other way round — a test
            // that forgets to say which it wants gets the scoped one.
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'muscle_group' => $group,
            'is_cardio' => $group === MuscleGroup::Cardio,
            'color' => $group->color(),
            'icon' => fake()->randomElement(ExerciseIcon::cases()),
        ];
    }

    /** A seeded movement everyone sees. */
    public function global(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    public function cardio(): static
    {
        return $this->state(fn () => [
            'muscle_group' => MuscleGroup::Cardio,
            'is_cardio' => true,
            'color' => MuscleGroup::Cardio->color(),
            'icon' => ExerciseIcon::Footprints,
        ]);
    }

    public function strength(): static
    {
        return $this->state(function () {
            $group = fake()->randomElement(array_filter(
                MuscleGroup::cases(),
                fn (MuscleGroup $g) => $g !== MuscleGroup::Cardio,
            ));

            return [
                'muscle_group' => $group,
                'is_cardio' => false,
                'color' => $group->color(),
                'icon' => ExerciseIcon::Dumbbell,
            ];
        });
    }
}
