<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * Token auth for mobile and third-party clients. The Inertia frontend keeps
 * using session auth and is unaffected.
 */
class AuthController extends Controller
{
    /**
     * Log in
     *
     * Issues a personal access token for a device.
     *
     * A wrong password and an unknown email return the identical 422 — a
     * distinguishable response would be a free user-enumeration oracle.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The account's email. Example: sam@example.com
     * @bodyParam password string required Example: secret
     * @bodyParam device_name string required Names the token so it can be revoked on its own later. Example: iPhone 15
     * @bodyParam abilities string[] Ask for a narrower token than the default. Intersected with what the user may grant, so it can never widen. Example: ["expenses:read"]
     *
     * @response 200 {"token": "3|kR9xLm...", "user": {"uuid": "0198f...", "name": "Sam", "email": "sam@example.com", "is_admin": false, "email_verified_at": "2026-07-16T10:00:00+00:00", "created_at": "2026-07-16T10:00:00+00:00"}}
     * @response 422 {"message": "These credentials do not match our records.", "errors": {"email": ["These credentials do not match our records."]}}
     * @response 429 {"message": "Too many attempts."}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // One combined check with one message: telling an attacker that the
        // email exists but the password is wrong is a free user-enumeration oracle.
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return response()->json([
            'token' => $this->issueToken($user, $request->device_name, $request->input('abilities')),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Register
     *
     * Creates an account and returns a token, mirroring the web register flow
     * (including the verification email).
     *
     * The new user always gets the `user` role — a `role` in the payload is
     * ignored, not honoured.
     *
     * @unauthenticated
     *
     * @bodyParam name string required Example: Sam
     * @bodyParam email string required Must not already be registered. Example: sam@example.com
     * @bodyParam password string required Example: Password123!
     * @bodyParam password_confirmation string required Must match password. Example: Password123!
     * @bodyParam device_name string required Example: iPhone 15
     *
     * @response 201 {"token": "3|kR9xLm...", "user": {"uuid": "0198f...", "name": "Sam", "email": "sam@example.com", "is_admin": false}}
     * @response 422 {"message": "The email has already been taken.", "errors": {"email": ["The email has already been taken."]}}
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            // Assigned explicitly — never from request input, or anyone could
            // register themselves an admin. applyRole and not assignRole: the
            // role carries the permissions, and permissions are the only thing
            // the policies read, so assignRole alone hands back a token that
            // 403s on everything.
            $user->applyRole(RoleName::User);

            return $user;
        });

        // Fires the verification mail, same as the web register flow.
        event(new Registered($user));

        return response()->json([
            'token' => $this->issueToken($user, $validated['device_name']),
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Current user
     *
     * @response 200 {"data": {"uuid": "0198f...", "name": "Sam", "email": "sam@example.com", "is_admin": false, "email_verified_at": "2026-07-16T10:00:00+00:00", "created_at": "2026-07-16T10:00:00+00:00"}}
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Log out
     *
     * Revokes **only the calling token**, so signing out on a phone leaves the
     * user's other devices signed in.
     *
     * @response 200 {"message": "Logged out."}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Logged out.')]);
    }

    /**
     * A client may ask for a narrower token than the default, but never a wider
     * one — anything it asks for is intersected with what the user may grant.
     *
     * @param  array<int, string>|null  $requested
     */
    private function issueToken(User $user, string $deviceName, ?array $requested = null): string
    {
        $grantable = TokenAbility::grantableTo($user);

        $abilities = $requested === null
            ? TokenAbility::defaults($user)
            : array_values(array_intersect($requested, $grantable));

        // An empty intersection would mint a token that can do nothing, which
        // looks like a broken login rather than a permissions problem.
        if ($abilities === []) {
            throw ValidationException::withMessages([
                'abilities' => [__('None of the requested abilities are available to this account.')],
            ]);
        }

        return $user->createToken($deviceName, $abilities)->plainTextToken;
    }
}
