<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutRequest;
use App\Models\ExerciseType;
use App\Models\Workout;
use App\Support\CalendarOptions;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WorkoutController extends Controller
{
    use PaginatesLists;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Workout::class);

        $query = QueryBuilder::for(Workout::class)
            ->allowedFilters(
                AllowedFilter::callback('from', fn ($query, $value) => $query->whereDate('performed_on', '>=', $value)),
                AllowedFilter::callback('to', fn ($query, $value) => $query->whereDate('performed_on', '<=', $value)),
                // Filter by the public UUID; the column itself stays internal.
                AllowedFilter::callback('exercise', fn ($query, $value) => $query->whereHas(
                    'sets.exerciseType',
                    fn ($q) => $q->whereIn('uuid', (array) $value),
                )),
            )
            ->allowedSorts('performed_on', 'duration_seconds')
            ->defaultSort('-performed_on', '-id')
            ->with(['sets.exerciseType:id,uuid,name,color,icon,muscle_group,is_cardio']);

        /*
         * Applied last so no filter can widen it beyond the owner's rows.
         *
         * There is no "everyone" scope here at all, unlike expenses: a training
         * log is personal, and WorkoutPolicy has no manage_all counterpart to
         * open one with.
         */
        $query->where('user_id', $request->user()->id);

        $workouts = $query->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('Exercise/Workouts/Index', [
            'workouts' => array_map($this->present(...), $workouts->items()),
            'pagination' => $this->paginationMeta($workouts),
            // Cast for the same reason as ExpenseController@index: an empty
            // only() is a JSON array, and filters.filter then resolves to
            // Array.prototype.filter instead of undefined.
            'filters' => (object) $request->only('filter', 'sort'),
            'months' => CalendarOptions::months(),
            'exercise_types' => $this->availableTypes($request),
            'can' => [
                'create' => $request->user()->can('create', Workout::class),
                'create_type' => $request->user()->can('create', ExerciseType::class),
            ],
        ]);
    }

    public function store(WorkoutRequest $request): RedirectResponse
    {
        Gate::authorize('create', Workout::class);

        DB::beginTransaction();

        try {
            // Created through the relationship so user_id is never mass-assignable.
            $workout = $request->user()->workouts()->create($request->workoutAttributes());

            $rows = $request->setRows();

            if ($rows !== []) {
                $workout->sets()->createMany($rows);
            }

            DB::commit();

            return redirect()->back()->withSuccess(__('Workout logged successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }
    }

    public function update(WorkoutRequest $request, Workout $workout): RedirectResponse
    {
        Gate::authorize('update', $workout);

        DB::beginTransaction();

        try {
            $workout->update($request->workoutAttributes());

            /*
             * Sets are replaced wholesale rather than diffed.
             *
             * A session is edited as one form — rows are added, removed and
             * reordered together — so matching them up by identity would mean
             * threading a client-side id through every row for no gain. The
             * delete and the insert share this transaction, so a failure leaves
             * the old sets in place rather than an empty session.
             */
            $workout->sets()->delete();

            $rows = $request->setRows();

            if ($rows !== []) {
                $workout->sets()->createMany($rows);
            }

            DB::commit();

            return redirect()->back()->withSuccess(__('Workout updated successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'))->withInput();
        }
    }

    public function destroy(Workout $workout): RedirectResponse
    {
        Gate::authorize('delete', $workout);

        DB::beginTransaction();

        try {
            // Sets go with it — the FK is cascadeOnDelete, so this is one row.
            $workout->delete();

            DB::commit();

            return redirect()->back()->withSuccess(__('Workout deleted successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

// getMessage() on a QueryException is the SQLSTATE, the whole
            // parameterised query and its bound values. That is a log entry,
            // not something to flash at whoever clicked the button.
            report($e);

            return redirect()->back()->withError(__('Something went wrong. Please try again.'));
        }
    }

    /**
     * Everything this person may log against: the globals plus their own.
     *
     * Mapped rather than passed straight through: Inertia serialises via
     * toArray(), and spatie/laravel-translatable does not translate there — the
     * raw {"en":…,"km":…} object would reach the picker. Reading ->name goes
     * through the accessor, which does translate.
     *
     * @return array<int, array<string, mixed>>
     */
    private function availableTypes(Request $request): array
    {
        return ExerciseType::query()
            ->availableTo((int) $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'color', 'icon', 'muscle_group', 'is_cardio', 'user_id'])
            ->map(fn (ExerciseType $type) => [
                'uuid' => $type->uuid,
                'name' => $type->name,
                'color' => $type->color?->value,
                'icon' => $type->icon?->value,
                'muscle_group' => $type->muscle_group?->value,
                'is_cardio' => $type->is_cardio,
                // Lets the picker mark which movements are the person's own.
                'is_mine' => ! $type->isGlobal(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Workout $workout): array
    {
        return [
            'uuid' => $workout->uuid,
            'performed_on' => $workout->performed_on->toDateString(),
            'duration_seconds' => $workout->duration_seconds,
            'notes' => $workout->notes,
            'volume_kg' => round($workout->volumeKg()),
            'sets' => $workout->sets->map(fn ($set) => [
                'uuid' => $set->uuid,
                'exercise_type_uuid' => $set->exerciseType?->uuid,
                'exercise' => $set->exerciseType?->name,
                'color' => $set->exerciseType?->color?->value,
                'icon' => $set->exerciseType?->icon?->value,
                'is_cardio' => (bool) $set->exerciseType?->is_cardio,
                'set_no' => $set->set_no,
                'reps' => $set->reps,
                // Always kilograms; the page converts for display using the
                // viewer's unit preference. See App\Enums\WeightUnit.
                'weight_kg' => $set->weight_kg === null ? null : (float) $set->weight_kg,
                'distance_m' => $set->distance_m,
                'duration_seconds' => $set->duration_seconds,
                'rpe' => $set->rpe,
            ])->values(),
        ];
    }
}
