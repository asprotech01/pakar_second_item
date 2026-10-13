<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->isAdmin() ? route('admin.dashboard') : route('dashboard'));
        }

        return view('auth.login');
    }

    public function registerForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->isAdmin() ? route('admin.dashboard') : route('dashboard'));
        }

        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $redirectRoute = Auth::user()->isAdmin() ? 'admin.dashboard' : 'dashboard';

            return redirect()->intended(route($redirectRoute));
        }

        return back()->withErrors([
            'email' => 'Kredensial tidak valid atau akun belum aktif.',
        ])->onlyInput('email');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_USER,
        ]);

        Auth::login($user);

        return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
