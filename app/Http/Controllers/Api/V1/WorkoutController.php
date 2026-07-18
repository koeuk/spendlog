<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkoutRequest;
use App\Http\Resources\ExerciseTypeResource;
use App\Http\Resources\WorkoutResource;
use App\Models\ExerciseType;
use App\Models\Workout;
use App\Services\MuscleGroupBreakdown;
use App\Services\WorkoutSummary;
use App\Support\CalendarOptions;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The exercise module's API half.
 *
 * Shares WorkoutRequest with the web controller, so validation and the
 * pounds→kilograms conversion cannot drift between the two. Authorization is
 * doubled as everywhere else in this API: the token's ability limits the
 * client, WorkoutPolicy limits the user, and both must pass.
 *
 * @group Exercise
 */
class WorkoutController extends Controller
{
    use PaginatesLists;

    /**
     * List workouts
     *
     * The caller's own sessions, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Workout::class);

        $workouts = QueryBuilder::for(Workout::class)
            ->allowedFilters(
                AllowedFilter::callback('from', fn ($query, $value) => $query->whereDate('performed_on', '>=', $value)),
                AllowedFilter::callback('to', fn ($query, $value) => $query->whereDate('performed_on', '<=', $value)),
            )
            ->allowedSorts('performed_on', 'duration_seconds')
            ->defaultSort('-performed_on', '-id')
            ->with(['sets.exerciseType'])
            // Applied last so no filter can widen it beyond the owner's rows.
            ->where('user_id', $request->user()->id)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return WorkoutResource::collection($workouts);
    }

    /**
     * Show a workout
     */
    public function show(Workout $workout): WorkoutResource
    {
        Gate::authorize('view', $workout);

        return new WorkoutResource($workout->load('sets.exerciseType'));
    }

    /**
     * Log a workout
     *
     * Send `sets` as an array; weights are read in `weight_unit` (kg or lb) and
     * stored as kilograms. Omitting the unit uses the app's configured default.
     */
    public function store(WorkoutRequest $request): JsonResponse
    {
        Gate::authorize('create', Workout::class);

        $workout = DB::transaction(function () use ($request) {
            $workout = $request->user()->workouts()->create($request->workoutAttributes());

            $rows = $request->setRows();

            if ($rows !== []) {
                $workout->sets()->createMany($rows);
            }

            return $workout;
        });

        return (new WorkoutResource($workout->load('sets.exerciseType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a workout
     *
     * Sets are replaced wholesale — send the full list, not a delta.
     */
    public function update(WorkoutRequest $request, Workout $workout): WorkoutResource
    {
        Gate::authorize('update', $workout);

        DB::transaction(function () use ($request, $workout) {
            $workout->update($request->workoutAttributes());

            // See the web controller: a session is edited as one form, so the
            // delete and the insert share this transaction.
            $workout->sets()->delete();

            $rows = $request->setRows();

            if ($rows !== []) {
                $workout->sets()->createMany($rows);
            }
        });

        return new WorkoutResource($workout->fresh()->load('sets.exerciseType'));
    }

    /**
     * Delete a workout
     */
    public function destroy(Workout $workout): JsonResponse
    {
        Gate::authorize('delete', $workout);

        // Sets go with it — the FK is cascadeOnDelete.
        $workout->delete();

        return response()->json(status: 204);
    }

    /**
     * List available exercises
     *
     * The shared catalogue plus anything the caller invented.
     */
    public function exercises(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ExerciseType::class);

        return ExerciseTypeResource::collection(
            ExerciseType::query()
                ->availableTo((int) $request->user()->id)
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * Training summary
     *
     * Sessions, volume, time and streak for one month (`?month=YYYY-MM`,
     * defaulting to the current one), plus the muscle-group split.
     */
    public function summary(
        Request $request,
        WorkoutSummary $summary,
        MuscleGroupBreakdown $breakdown,
    ): JsonResponse {
        Gate::authorize('viewAny', Workout::class);

        $user = $request->user();
        $month = CalendarOptions::resolveMonth($request->query('month'));

        return response()->json([
            'data' => [
                'month' => $month->format('Y-m'),
                ...$summary->forMonth($user, $month),
                'streak' => $summary->currentStreak($user),
                'breakdown' => $breakdown->forMonth($user, $month),
                'records' => $summary->personalRecords($user),
            ],
        ]);
    }
}
