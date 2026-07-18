<?php

namespace App\Http\Controllers\Exercise;

use App\Enums\CategoryColor;
use App\Enums\ExerciseIcon;
use App\Enums\MuscleGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExerciseTypeRequest;
use App\Models\ExerciseType;
use App\Support\TranslatableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExerciseTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExerciseType::class);

        $user = $request->user();

        $types = QueryBuilder::for(ExerciseType::class)
            ->allowedFilters(
                TranslatableQuery::filter('name'),
                AllowedFilter::exact('muscle_group'),
                // "Mine" vs "All" — the only scope that exists here, since the
                // globals are visible to everyone by design.
                AllowedFilter::callback('mine', fn ($query, $value) => filter_var($value, FILTER_VALIDATE_BOOL)
                    ? $query->whereNotNull('user_id')
                    : $query),
            )
            ->allowedSorts(TranslatableQuery::sort('name'), 'muscle_group')
            ->defaultSort('muscle_group', TranslatableQuery::sort('name'))
            // Applied last so no filter can widen it past what this person sees.
            ->availableTo((int) $user->id)
            ->get();

        return Inertia::render('Exercise/Types/Index', [
            'types' => $types->map(fn (ExerciseType $type) => [
                'uuid' => $type->uuid,
                // Two shapes on purpose: the list renders the active locale,
                // while the edit dialog has to populate a field per locale.
                'name' => $type->name,
                'name_translations' => $type->getTranslations('name'),
                'muscle_group' => $type->muscle_group?->value,
                'is_cardio' => $type->is_cardio,
                'color' => $type->color?->value,
                'icon' => $type->icon?->value,
                'is_mine' => ! $type->isGlobal(),
                // Per-row, because the two tiers answer differently: a global is
                // editable only with types_manage, one of theirs with update.
                'can_update' => $request->user()->can('update', $type),
                'can_delete' => $request->user()->can('delete', $type),
            ])->values(),
            'filters' => (object) $request->only('filter', 'sort'),
            'muscle_groups' => MuscleGroup::options(),
            'colors' => array_map(fn (CategoryColor $c) => $c->value, CategoryColor::cases()),
            'icons' => array_map(fn (ExerciseIcon $i) => $i->value, ExerciseIcon::cases()),
            'can' => [
                'create' => $request->user()->can('create', ExerciseType::class),
            ],
        ]);
    }

    public function store(ExerciseTypeRequest $request): RedirectResponse
    {
        Gate::authorize('create', ExerciseType::class);

        DB::beginTransaction();

        try {
            /*
             * Created through the relationship, so user_id is set from the
             * authenticated user and never from input. This is what makes every
             * type created here a personal one — promoting a movement to the
             * global catalogue is a seeder's job, not a request's.
             */
            $request->user()->exerciseTypes()->create($request->exerciseTypeAttributes());

            DB::commit();

            return redirect()->back()->withSuccess(__('Exercise added successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    public function update(ExerciseTypeRequest $request, ExerciseType $exerciseType): RedirectResponse
    {
        Gate::authorize('update', $exerciseType);

        DB::beginTransaction();

        try {
            $exerciseType->update($request->exerciseTypeAttributes());

            DB::commit();

            return redirect()->back()->withSuccess(__('Exercise updated successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    public function destroy(ExerciseType $exerciseType): RedirectResponse
    {
        Gate::authorize('delete', $exerciseType);

        DB::beginTransaction();

        try {
            $exerciseType->delete();

            DB::commit();

            return redirect()->back()->withSuccess(__('Exercise deleted successfully.'));
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollback();

            /*
             * The FK is restrictOnDelete, so a movement that has been performed
             * cannot be removed — deleting it would silently rewrite history.
             * 23000 is the integrity-constraint class; anything else is a real
             * failure and should not be dressed up as this message.
             */
            if ($e->getCode() === '23000') {
                return redirect()->back()->withError(
                    __('That exercise has been logged in a workout, so it cannot be deleted.'),
                );
            }

            return redirect()->back()->withError($e->getMessage());
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withError($e->getMessage());
        }
    }
}
