<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user()->load('roles.permissions', 'directPermissions');

        $stats = [
            'campaigns' => $user->campaigns()->count(),
            'contact_lists' => $user->contactLists()->count(),
            'templates' => $user->templates()->count(),
            'smtp_configs' => $user->smtpConfigs()->count(),
        ];

        return view('profile.edit', [
            'user' => $user,
            'stats' => $stats,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'job_title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($user);
            $validated['avatar_path'] = $request->file('avatar')->store('avatars/'.$user->id, 'local');
        }

        unset($validated['avatar']);
        $validated['timezone'] = ($validated['timezone'] ?? null) ?: 'UTC';

        $user->update($validated);

        return redirect()->route('profile.edit')->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.']);
        }

        $user->update(['password' => $validated['password']]);

        // A password change should end any session the old password established
        // elsewhere — that is the whole point of changing it after a compromise.
        Auth::logoutOtherDevices($validated['password']);

        return redirect()->route('profile.edit')->with('success', 'Your password has been changed.');
    }

    public function destroyAvatar()
    {
        $user = Auth::user();
        $this->deleteAvatar($user);
        $user->update(['avatar_path' => null]);

        return redirect()->route('profile.edit')->with('success', 'Profile photo removed.');
    }

    /**
     * Avatars live on the private local disk, so they are streamed back through
     * the app rather than served straight out of public/.
     */
    public function avatar(User $user)
    {
        // Route-model bound, so the id comes straight off the URL. Your own photo
        // is always fine; anyone else's requires the permission that already
        // exposes the user directory, which is what renders these in admin lists.
        if ($user->id !== Auth::id() && ! Auth::user()->hasPermission('users.view')) {
            abort(403);
        }

        if (! $user->avatar_path || ! Storage::disk('local')->exists($user->avatar_path)) {
            abort(404);
        }

        return $this->streamPrivateImage(Storage::disk('local')->path($user->avatar_path));
    }

    /**
     * Streams an uploaded image off the private disk.
     *
     * `nosniff` plus an explicit inline disposition matter here: BinaryFileResponse
     * guesses the content type from the bytes, and a polyglot that satisfied the
     * `mimes:` rule could otherwise be sniffed as HTML and run on this origin.
     */
    protected function streamPrivateImage(string $absolutePath): BinaryFileResponse
    {
        $mime = @mime_content_type($absolutePath) ?: 'application/octet-stream';

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'application/octet-stream';
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setMaxAge(3600);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', 'inline');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    protected function deleteAvatar(User $user): void
    {
        if ($user->avatar_path && Storage::disk('local')->exists($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }
    }
}
