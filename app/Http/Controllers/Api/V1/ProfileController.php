<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * @group Profile
 *
 * The account's own details. Requires the `profile:write` ability — which is
 * **not** in a default token unless the account holds the profile permissions.
 * The photo endpoints are the exception: open to any signed-in account.
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
     * @bodyParam phone string A contact number, or blank to clear it. Example: +855 12 345 678
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

    /** Where profile photos live on the 'public' disk. */
    private const AVATAR_DIR = 'avatars';

    /**
     * Upload a profile photo
     *
     * Multipart, field `avatar`. Replaces any existing photo and deletes the
     * old file, so the disk does not fill with orphans. No ability or
     * permission is needed beyond being signed in.
     *
     * @bodyParam avatar file required A JPEG, PNG or WebP up to 4 MB.
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Koeuk", "avatar_url": "http://.../storage/avatars/abc.jpg?v=1725000000"}}
     * @response 422 {"message": "The avatar field must be an image."}
     */
    public function storeAvatar(Request $request): UserResource
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->avatar_path = $request->file('avatar')->store(self::AVATAR_DIR, 'public');
        $user->save();

        if ($previous && $previous !== $user->avatar_path) {
            Storage::disk('public')->delete($previous);
        }

        return new UserResource($user);
    }

    /**
     * Remove the profile photo
     *
     * Deletes the file and clears the path. Idempotent: an account with no
     * photo gets the same 200 back.
     *
     * @response 200 {"data": {"uuid": "0198a...", "name": "Koeuk", "avatar_url": null}}
     */
    public function destroyAvatar(Request $request): UserResource
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);

            $user->avatar_path = null;
            $user->save();
        }

        return new UserResource($user);
    }
}
