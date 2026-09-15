<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileAvatarRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    /**
     * The form itself is rendered by SettingsController; this class only writes.
     *
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        Gate::authorize('updateProfile');

        // profileAttributes(), not validated(): a blank username has to reach the
        // column as null rather than '', or the unique index treats the empty
        // string as a handle and only one account may have "no username".
        $request->user()->fill($request->profileAttributes());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Replace the profile photo.
     *
     * No updateProfile gate, unlike update() above: the photo is open to any
     * signed-in account, the same rule as the API's profile/avatar endpoints,
     * so the two doors agree on who may change it.
     */
    public function storeAvatar(ProfileAvatarRequest $request): RedirectResponse
    {
        $request->user()->storeAvatar($request->file('avatar'));

        return Redirect::route('profile.edit')->with('success', __('Photo updated.'));
    }

    /**
     * Remove the profile photo. Idempotent — an account with no photo lands
     * back on the page all the same.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $request->user()->removeAvatar();

        return Redirect::route('profile.edit')->with('success', __('Photo removed.'));
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
