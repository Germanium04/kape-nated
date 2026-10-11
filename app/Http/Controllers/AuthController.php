<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS  = 5;   // failed logins allowed...
    private const DECAY_SECONDS = 60;  // ...per this window, per username + IP

    private const VALID_ROLES = ['admin', 'staff'];

    public function showLogin(): View
    {
        return view('layouts.authentication.Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Brute-force protection. Auth::attempt() does NOT throttle on its own.
        $throttleKey = Str::lower($credentials['username']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors(['username' => "Too many login attempts. Try again in {$seconds} seconds."])
                ->onlyInput('username');
        }

        try {
            $ok = Auth::attempt($credentials);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withErrors(['general' => 'Something went wrong on our side. Please try again.'])
                ->onlyInput('username');
        }

        if (! $ok) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            // Same message for "no such user" and "wrong password" on purpose,
            // so nobody can use the form to discover which usernames exist.
            return back()
                ->withErrors(['username' => 'Incorrect username or password.'])
                ->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate(); // prevents session fixation

        $user = Auth::user();

        // An account with an unknown/empty role would otherwise bounce around forever.
        if (! in_array($user->role, self::VALID_ROLES, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('authentication.login')
                ->with('error', 'Your account has no valid role. Please contact an administrator.');
        }

        return redirect()
            ->route($user->role === 'admin' ? 'admin.dashboard' : 'staff.orders')
            ->with('success', "Welcome back, {$user->name}!");
    }

    public function showSignup(): View
    {
        return view('layouts.authentication.Signup', [
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function signup(Request $request)
    {
        $validated = $request->validate([
            'fname'    => ['required', 'string', 'max:100'],
            'lname'    => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'contact'  => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/', 'unique:users,contact'],
            'branch'   => ['required', 'string', 'exists:branches,name'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ], [
            'username.unique'  => 'That username is already taken.',
            'contact.unique'   => 'That contact number is already registered.',
            'contact.regex'    => 'Enter a valid contact number.',
            'branch.exists'    => 'Please choose a branch from the list.',
        ]);

        try {
            $branch = Branch::where('name', $validated['branch'])->firstOrFail();

            $user = User::create([
                'name'      => $validated['fname'] . ' ' . $validated['lname'],
                'username'  => $validated['username'],
                'contact'   => $validated['contact'],
                'branch_id' => $branch->id,
                'role'      => 'staff', // never taken from the request
                'password'  => bcrypt($validated['password']),
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['general' => 'We could not create your account right now. Please try again.']);
        }

        Auth::login($user);
        $request->session()->regenerate(); // was missing: session fixation risk

        // Was 'admin.dashboard' — a staff account would be bounced by the role check.
        return redirect()->route('staff.orders')
            ->with('success', 'Account created. Welcome to Kape-nated!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Flashed AFTER invalidate() so it lands in the fresh session.
        return redirect()->route('authentication.login')
            ->with('success', 'You have been logged out.');
    }
}