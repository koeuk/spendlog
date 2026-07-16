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

class AuthController extends Controller
{
    /**
     * Issue a personal access token. The Inertia frontend keeps using session
     * auth — this is for mobile and third-party clients.
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
            // register themselves an admin.
            $user->assignRole(RoleName::User->value);

            return $user;
        });

        // Fires the verification mail, same as the web register flow.
        event(new Registered($user));

        return response()->json([
            'token' => $this->issueToken($user, $validated['device_name']),
            'user' => new UserResource($user),
        ], 201);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Revokes only the token that made this call, so logging out on a phone
     * does not sign the user out of their other devices.
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
