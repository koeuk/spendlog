<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;

/**
 * The headline figures on the exercise dashboard: what happened this month, and
 * how consistent the person has been.
 *
 * The counterpart to BudgetSummary — one place the numbers are computed, so the
 * cards and any future report cannot disagree about what "this month" means.
 */
class WorkoutSummary
{
    /**
     * Sessions, volume and time for one month.
     *
     * @return array{sessions: int, volume_kg: float, duration_seconds: int, sets: int}
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();

        $workouts = Workout::query()
            ->where('user_id', $user->id)
            ->whereBetween('performed_on', [$start, $end]);

        $volume = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->whereBetween('workouts.performed_on', [$start, $end])
            ->whereNotNull('workout_sets.reps')
            ->whereNotNull('workout_sets.weight_kg')
            ->selectRaw('SUM(workout_sets.reps * workout_sets.weight_kg) as volume, COUNT(*) as sets')
            ->first();

        return [
            'sessions' => (int) $workouts->clone()->count(),
            // Coalesced: SUM over an empty set is NULL, not 0.
            'volume_kg' => round((float) ($volume->volume ?? 0)),
            'duration_seconds' => (int) $workouts->clone()->sum('duration_seconds'),
            'sets' => (int) ($volume->sets ?? 0),
        ];
    }

    /**
     * Consecutive days trained, counting back from today.
     *
     * Today not being trained yet does not break the streak — it is still early.
     * So the count starts at yesterday if today is empty, and only a fully
     * missed day ends it. Without that rule the streak would read 0 every
     * morning until the person trained, which is both wrong and demoralising.
     *
     * Reads distinct dates once and walks them in PHP rather than issuing a
     * query per day.
     */
    public function currentStreak(User $user, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now()->startOfDay();

        $days = Workout::query()
            ->where('user_id', $user->id)
            ->where('performed_on', '<=', $today->toDateString())
            ->orderByDesc('performed_on')
            ->distinct()
            ->pluck('performed_on')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return 0;
        }

        $trained = $days->flip();

        // Start today if trained, else yesterday — see the note above.
        $cursor = $trained->has($today->toDateString()) ? $today : $today->subDay();
        $streak = 0;

        while ($trained->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /**
     * The dates trained in a window, for the consistency heatmap.
     *
     * Returned as a flat list of 'Y-m-d' rather than a per-day grid: the grid's
     * shape is a presentation choice, and building it here would pin the
     * frontend to one layout.
     *
     * @return array<int, string>
     */
    public function trainedDays(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Workout::query()
            ->where('user_id', $user->id)
            ->whereBetween('performed_on', [$from->toDateString(), $to->toDateString()])
            ->distinct()
            ->pluck('performed_on')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The heaviest set ever recorded for each movement — the PR list.
     *
     * Heaviest weight, not estimated one-rep-max: a formula would invent a
     * number the person never actually lifted, and the point of this list is to
     * show what they did.
     *
     * @return array<int, array{name: string, color: string, icon: string|null, weight_kg: float, reps: int, performed_on: string}>
     */
    public function personalRecords(User $user, int $limit = 6): array
    {
        /*
         * The heaviest set per type, then the row it came from.
         *
         * Done as a join against the per-type maximum rather than a plain
         * GROUP BY: selecting reps and the date alongside MAX(weight) would
         * return values from an arbitrary row of the group under
         * only_full_group_by, not from the heaviest one.
         */
        $best = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->whereNotNull('workout_sets.weight_kg')
            ->groupBy('workout_sets.exercise_type_id')
            ->selectRaw('workout_sets.exercise_type_id as type_id, MAX(workout_sets.weight_kg) as best')
            ->pluck('best', 'type_id');

        if ($best->isEmpty()) {
            return [];
        }

        $records = [];

        foreach ($best as $typeId => $weight) {
            $set = WorkoutSet::query()
                ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
                ->where('workouts.user_id', $user->id)
                ->where('workout_sets.exercise_type_id', $typeId)
                ->where('workout_sets.weight_kg', $weight)
                // The earliest date it was hit: a PR belongs to when it was set.
                ->orderBy('workouts.performed_on')
                ->with('exerciseType')
                ->select('workout_sets.*', 'workouts.performed_on as performed_on')
                ->first();

            if (! $set || ! $set->exerciseType) {
                continue;
            }

            $records[] = [
                'name' => $set->exerciseType->name,
                'color' => $set->exerciseType->color?->value ?? 'slate',
                'icon' => $set->exerciseType->icon?->value,
                'weight_kg' => (float) $weight,
                'reps' => (int) $set->reps,
                'performed_on' => CarbonImmutable::parse($set->performed_on)->toDateString(),
            ];
        }

        usort($records, fn (array $a, array $b) => $b['weight_kg'] <=> $a['weight_kg']);

        return array_slice($records, 0, $limit);
    }
}
