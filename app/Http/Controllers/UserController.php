<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $me = $request->user();

        $users = User::query()
            ->with('roles:id,name')
            ->withCount('expenses')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? RoleName::User->value,
                'status' => $user->status->value,
                'status_label' => $user->status->label(),
                'status_classes' => $user->status->badgeClasses(),
                'expenses_count' => $user->expenses_count,
                'verified' => $user->hasVerifiedEmail(),
                // Marks the row as the viewer's own, so the UI can say why the
                // actions are missing instead of just hiding them.
                'is_self' => $me->is($user),
                // Computed per row: the last-admin rule means these differ from
                // row to row, so one page-level "can manage" flag would be wrong.
                'can' => [
                    'update' => $me->can('update', $user),
                    'delete' => $me->can('delete', $user),
                    'suspend' => $me->can('suspend', $user),
                    'change_role' => $me->can('changeRole', $user),
                ],
            ]);

        return Inertia::render('Settings/Users', [
            'users' => $users,
            'roles' => array_map(
                fn (RoleName $role) => ['value' => $role->value, 'label' => ucfirst($role->value)],
                RoleName::cases(),
            ),
            'statuses' => UserStatus::options(),
            'can' => ['create' => $me->can('create', User::class)],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        DB::beginTransaction();

        try {
            $user = new User($request->userAttributes());
            $user->save();

            // Assigned explicitly, never mass-assigned — otherwise the request
            // body could hand out admin.
            $user->syncRoles([$request->validated('role')]);

            DB::commit();

            // Same verification mail the public register flow sends.
            event(new Registered($user));

            return back()->withSuccess(__('User created successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withError($e->getMessage())->withInput();
        }
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $role = $request->validated('role');
        $status = $request->validated('status');

        // A role change and a suspension are separately gated: 'update' allows
        // editing a name, but neither of these — both can strand the install
        // without an admin, or lock the current one out.
        if ($role !== ($user->roles->first()?->name ?? RoleName::User->value)) {
            Gate::authorize('changeRole', $user);
        }

        if ($status !== $user->status->value) {
            Gate::authorize('suspend', $user);
        }

        DB::beginTransaction();

        try {
            $user->fill($request->userAttributes());

            // Re-verification is required when the address changes, matching the
            // rule the user's own profile form follows.
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            $user->syncRoles([$role]);

            DB::commit();

            return back()->withSuccess(__('User updated successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withError($e->getMessage())->withInput();
        }
    }

    /** Suspend/activate on its own, so the common case is one click. */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('suspend', $user);

        DB::beginTransaction();

        try {
            $user->status = $user->isSuspended() ? UserStatus::Active : UserStatus::Suspended;
            $user->save();

            if ($user->isSuspended()) {
                // The web session dies via EnsureUserIsActive on their next
                // request; API tokens have no such checkpoint, so they go now.
                $user->tokens()->delete();
            }

            DB::commit();

            return back()->withSuccess(
                $user->isSuspended()
                    ? __(':name has been suspended.', ['name' => $user->name])
                    : __(':name is active again.', ['name' => $user->name]),
            );
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withError($e->getMessage());
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        DB::beginTransaction();

        try {
            // expenses/budgets cascade on the FK — deleting an account takes its
            // whole history with it, which is why the UI confirms with a count.
            $user->delete();

            DB::commit();

            return back()->withSuccess(__('User deleted successfully.'));
        } catch (\Exception $e) {
            DB::rollback();

            return back()->withError($e->getMessage());
        }
    }
}
