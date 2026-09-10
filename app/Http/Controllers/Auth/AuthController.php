<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            if (! Auth::user()->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact an administrator.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            Auth::user()->forceFill(['last_login_at' => now()])->save();

            return redirect()->intended(route('dashboard'))->with('success', 'Welcome back, '.Auth::user()->name.'!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        $this->abortUnlessRegistrationOpen();

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $this->abortUnlessRegistrationOpen();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        // The very first account to register owns the platform; everyone after
        // it starts on the Member role until an admin promotes them. Creating
        // the account and claiming the role happen in one transaction, so two
        // simultaneous first registrations cannot both become super admin.
        $user = DB::transaction(function () use ($validated) {
            $isFirstUser = User::lockForUpdate()->count() === 0;

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'last_login_at' => now(),
            ]);

            $defaultRole = Role::where('slug', $isFirstUser ? Role::SUPER_ADMIN : 'member')->first();

            if ($defaultRole) {
                $user->roles()->attach($defaultRole);
            }

            return $user;
        });

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Account created successfully! Welcome to MailFlow.');
    }

    /**
     * Public signup is off unless explicitly enabled (config/mailflow.php).
     * 404 rather than 403, so a closed instance does not advertise the feature.
     */
    protected function abortUnlessRegistrationOpen(): void
    {
        abort_unless(config('mailflow.allow_registration', false), 404);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been logged out successfully.');
    }
}
