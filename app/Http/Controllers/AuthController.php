<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Branch;
use App\Models\User;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('layouts.authentication.Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();
        }

        if ($user->role == 'admin'){
            return redirect()->route('admin.dashboard');
        } else {
            return redirect()->route('staff.dashboard');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function showSignup(): View
    {
        return view('layouts.authentication.Signup');
    }

    public function signup(Request $request)
    {
        $validatedData = $request->validate([
            'fname'    => ['required', 'string', 'max:255'],
            'lname'    => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'contact'  => ['required', 'string', 'max:20', 'unique:users'],
            'branch'   => ['required', 'string', 'exists:branches,name'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $branch = Branch::where('name', $validatedData['branch'])->firstOrFail();

        $user = User::create([
            'name'      => $validatedData['fname'] . ' ' . $validatedData['lname'],
            'username'  => $validatedData['username'],
            'contact'   => $validatedData['contact'],
            'branch_id' => $branch->id,
            'role'      => 'staff',
            'password'  => bcrypt($validatedData['password']),
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