<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @group Profile
 *
 * @authenticated
 */
class PasswordController extends Controller
{
    /**
     * Change the password
     *
     * No current-password check, mirroring the web form — accounts here are
     * created by an admin for a small known group, and the `profile:write`
     * ability plus the gate already bound who can reach this.
     *
     * @bodyParam password string required The new password. Example: a-much-better-one
     * @bodyParam password_confirmation string required Example: a-much-better-one
     *
     * @response 200 {"message": "Password changed."}
     * @response 403 scenario="token lacks profile:write, or user lacks the permission" {"message": "This action is unauthorized."}
     */
    public function update(Request $request): JsonResponse
    {
        Gate::authorize('updatePassword');

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => __('Password changed.')]);
    }
}
