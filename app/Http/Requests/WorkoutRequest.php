<?php

namespace App\Http\Requests;

use App\Enums\WeightUnit;
use App\Models\ExerciseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A workout is submitted whole — the session and its sets in one request — so
 * this validates a nested array and hands back two shapes: the workout's own
 * columns, and the rows for its sets.
 */
class WorkoutRequest extends FormRequest
{
    /**
     * Authorization is handled by WorkoutPolicy via the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'performed_on' => ['required', 'date', 'before_or_equal:today'],
            // The stopwatch writes seconds here. Capped at 24h so a timer left
            // running overnight cannot store a session longer than a day.
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // What the weights below are denominated in. Absent means kilograms,
            // so a client that never sends it keeps working — mirrors 'currency'
            // on ExpenseRequest.
            'weight_unit' => ['nullable', Rule::enum(WeightUnit::class)],

            /*
             * A session with no sets is allowed: a timed run logged from the
             * stopwatch alone is a real workout. The dashboard counts it, it
             * just contributes no volume.
             */
            'sets' => ['nullable', 'array', 'max:200'],
            'sets.*.exercise_type_uuid' => [
                'required',
                'uuid',
                // Existence alone is not enough — it has to be one this person
                // may actually log against, or a guessed UUID would file a set
                // under somebody else's private movement.
                function (string $attribute, mixed $value, \Closure $fail) {
                    $available = ExerciseType::query()
                        ->where('uuid', $value)
                        ->availableTo((int) $this->user()->id)
                        ->exists();

                    if (! $available) {
                        $fail(__('That exercise is not available to you.'));
                    }
                },
            ],
            'sets.*.reps' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'sets.*.weight' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'sets.*.distance_m' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sets.*.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'sets.*.rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'performed_on.before_or_equal' => __('You cannot log a workout in the future.'),
            'performed_on.required' => __('Please pick the date you trained.'),
        ];
    }

    /**
     * The workout's own columns.
     *
     * @return array<string, mixed>
     */
    public function workoutAttributes(): array
    {
        return [
            'performed_on' => $this->validated('performed_on'),
            'duration_seconds' => $this->validated('duration_seconds'),
            'notes' => $this->validated('notes'),
        ];
    }

    /**
     * The rows for this workout's sets, ready to hand to sets()->createMany().
     *
     * Two things happen here, both for the same reason the expense request
     * converts currency: the frontend only ever sees UUIDs, and every weight is
     * stored in kilograms regardless of what was typed.
     *
     * set_no is assigned from the array order rather than trusted from input —
     * it is a display position, and letting the client pick it invites two sets
     * numbered 1 and a list that cannot be read back in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function setRows(): array
    {
        $sets = $this->validated('sets') ?? [];

        if ($sets === []) {
            return [];
        }

        $unit = WeightUnit::tryFrom((string) $this->input('weight_unit')) ?? WeightUnit::Kg;
        $ids = $this->exerciseTypeIds($sets);

        $rows = [];
        $position = 0;

        foreach ($sets as $set) {
            $typeId = $ids[$set['exercise_type_uuid']] ?? null;

            // Validation already refused unknown UUIDs; this guards the race
            // where the type is deleted between validating and writing.
            if ($typeId === null) {
                continue;
            }

            $weight = $set['weight'] ?? null;

            $rows[] = [
                'exercise_type_id' => $typeId,
                'set_no' => ++$position,
                'reps' => $set['reps'] ?? null,
                'weight_kg' => $weight === null ? null : $unit->toKg($weight),
                'distance_m' => $set['distance_m'] ?? null,
                'duration_seconds' => $set['duration_seconds'] ?? null,
                'rpe' => $set['rpe'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * UUID => internal id for every type referenced, in one query rather than
     * one per set — a 40-set session would otherwise cost 40 lookups.
     *
     * @param  array<int, array<string, mixed>>  $sets
     * @return array<string, int>
     */
    private function exerciseTypeIds(array $sets): array
    {
        $uuids = array_values(array_unique(array_column($sets, 'exercise_type_uuid')));

        return ExerciseType::query()
            ->whereIn('uuid', $uuids)
            ->availableTo((int) $this->user()->id)
            ->pluck('id', 'uuid')
            ->all();
    }
}
