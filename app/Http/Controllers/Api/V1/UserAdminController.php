<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Support\Concerns\PaginatesLists;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Users
 *
 * The user-management desk over the API — the same rules the web screen
 * enforces: UserPolicy decides per row, UserRequest validates, and roles are
 * assigned explicitly rather than mass-assigned. Needs `users:read` /
 * `users:write`, which only an account with the users.* permissions can hold.
 *
 * @authenticated
 */
class UserAdminController extends Controller
{
    use PaginatesLists;

    /**
     * List users
     *
     * @queryParam page integer Example: 1
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $paginator = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map($this->row(...))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Create a user
     *
     * @bodyParam name string required Example: Sok Dara
     * @bodyParam email string required Example: dara@example.com
     * @bodyParam username string A display handle, or blank. Example: dara
     * @bodyParam password string required Example: a-strong-one
     * @bodyParam password_confirmation string required Example: a-strong-one
     * @bodyParam role string required One of the assignable roles — super_admin is not one. Example: user
     * @bodyParam status string required active, invited, suspended or archived. Example: active
     */
    public function store(UserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $user = DB::transaction(function () use ($request) {
            $user = new User($request->userAttributes());
            $user->save();

            // Assigned explicitly, never mass-assigned — otherwise the request
            // body could hand out admin.
            $user->applyRole(RoleName::from($request->validated('role')));

            return $user;
        });

        // Same verification mail the public register flow sends.
        event(new Registered($user));

        return response()->json(['data' => $this->row($user->fresh('roles'))], 201);
    }

    /**
     * Update a user
     *
     * Role and status changes are separately gated, exactly as on the web:
     * either can strand the install without an admin.
     */
    public function update(UserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $role = $request->validated('role');
        $status = $request->validated('status');

        $roleChanged = $role !== ($user->roles->first()?->name ?? RoleName::User->value);

        if ($roleChanged) {
            Gate::authorize('changeRole', $user);
        }

        if ($status !== $user->status->value) {
            Gate::authorize('suspend', $user);
        }

        DB::transaction(function () use ($request, $user, $role, $roleChanged) {
            $user->fill($request->userAttributes());

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            // Suspending must kill live tokens, or a phone keeps working
            // after the web session dies.
            if ($user->status->revokesAccess()) {
                $user->tokens()->delete();
            }

            if ($roleChanged) {
                $user->applyRole(RoleName::from($role));
            }
        });

        return response()->json(['data' => $this->row($user->fresh('roles'))]);
    }

    /**
     * Delete a user
     *
     * @response 204 scenario=deleted {}
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        DB::transaction(fn () => $user->delete());

        return response()->json([], 204);
    }

    /**
     * Set a user's photo
     *
     * Multipart, field `avatar`. The same store as the self-service endpoint,
     * gated by UserPolicy::update — so a super admin's photo is as out of
     * reach as the rest of their account.
     *
     * @urlParam user string required The user UUID.
     *
     * @bodyParam avatar file required A JPEG, PNG or WebP up to 4 MB.
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Sam", "avatar_url": "http://.../storage/avatars/abc.jpg?v=1725000000", "role": "user", "status": "active"}}
     * @response 403 scenario="target out of reach or missing users.manage" {"message": "This action is unauthorized."}
     */
    public function storeAvatar(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user->storeAvatar($request->file('avatar'));

        return response()->json(['data' => $this->row($user->load('roles'))]);
    }

    /**
     * Remove a user's photo
     *
     * @urlParam user string required The user UUID.
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Sam", "avatar_url": null, "role": "user", "status": "active"}}
     */
    public function destroyAvatar(User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $user->removeAvatar();

        return response()->json(['data' => $this->row($user->load('roles'))]);
    }

    /**
     * One row, shaped for an admin list — carries what UserResource hides
     * from ordinary callers: the role and the status.
     *
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'role' => $user->roles->first()?->name ?? RoleName::User->value,
            'status' => $user->status->value ?? UserStatus::Active->value,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
