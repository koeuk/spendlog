<?php

namespace App\Services;

use App\Enums\MuscleGroup;
use App\Models\User;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;

/**
 * Where a month's training went, by muscle group — the exercise counterpart to
 * CategoryBreakdown.
 *
 * Two measures, not one, because cardio has no weight: volume answers "what did
 * I load" and sets answers "what did I actually do". A month of running would
 * be invisible on volume alone.
 */
class MuscleGroupBreakdown
{
    /**
     * @return array<int, array{group: string, label: string, color: string, volume_kg: float, sets: int, share: float}>
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $rows = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->join('exercise_types', 'exercise_types.id', '=', 'workout_sets.exercise_type_id')
            ->where('workouts.user_id', $user->id)
            ->whereBetween('workouts.performed_on', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('exercise_types.muscle_group')
            ->selectRaw('exercise_types.muscle_group as muscle_group')
            // COALESCE, not a bare SUM: a group with only cardio sets has no
            // weights at all, and SUM would hand back NULL for it.
            ->selectRaw('COALESCE(SUM(workout_sets.reps * workout_sets.weight_kg), 0) as volume')
            ->selectRaw('COUNT(*) as sets')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $totalSets = (int) $rows->sum('sets');

        $breakdown = $rows->map(function ($row) use ($totalSets) {
            $group = MuscleGroup::tryFrom($row->muscle_group) ?? MuscleGroup::FullBody;

            return [
                'group' => $group->value,
                'label' => $group->label(),
                'color' => $group->color()->value,
                'volume_kg' => round((float) $row->volume),
                'sets' => (int) $row->sets,
                /*
                 * Share is of *sets*, not volume, on purpose. Sharing by volume
                 * would give cardio a flat 0% and read as "you did no cardio"
                 * when the person ran four times — the exact misreading this
                 * chart exists to prevent.
                 */
                'share' => $totalSets > 0 ? round($row->sets / $totalSets * 100, 1) : 0.0,
            ];
        })->all();

        usort($breakdown, fn (array $a, array $b) => $b['sets'] <=> $a['sets']);

        return $breakdown;
    }
}
