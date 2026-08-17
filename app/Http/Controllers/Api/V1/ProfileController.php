<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Gate;

/**
 * @group Profile
 *
 * The account's own details. Requires the `profile:write` ability — which is
 * **not** in a default token unless the account holds the profile permissions.
 *
 * @authenticated
 */
class ProfileController extends Controller
{
    /**
     * Update the profile
     *
     * Same rules as the web form: the username is a display handle (blank
     * releases it), and changing the email clears its verified timestamp.
     *
     * @bodyParam name string required Example: Koeuk
     * @bodyParam username string A display handle, or blank to release it. Example: koeuk
     * @bodyParam email string required Example: koeukkos@gmail.com
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Koeuk", "email": "koeukkos@gmail.com"}}
     * @response 403 scenario="token lacks profile:write, or user lacks the permission" {"message": "This action is unauthorized."}
     */
    public function update(ProfileUpdateRequest $request): UserResource
    {
        Gate::authorize('updateProfile');

        // profileAttributes(), not validated(): a blank username has to reach
        // the column as null rather than '' — see the web ProfileController.
        $request->user()->fill($request->profileAttributes());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return new UserResource($request->user());
    }
}
