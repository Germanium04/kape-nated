<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authentication extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function signup(Request $request)
    {
        $validatedData = $request->only([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'contact' => ['required', 'string', 'max:20'],
            'branch' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Create a new user with the validated data
        $user = \App\Models\User::create([
            'name' => $validatedData['fname'] . ' ' . $validatedData['lname'],
            'username' => $validatedData['username'],
            'contact' => $validatedData['contact'],
            'branch' => $validatedData['branch'],
            'role' => 'staff',
            'password' => bcrypt($validatedData['password']),
        ]);

        Auth::login($user);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('authentication.login');
    }
}