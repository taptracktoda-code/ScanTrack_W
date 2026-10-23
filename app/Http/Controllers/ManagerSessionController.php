<?php

namespace App\Http\Controllers;

use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ManagerSessionController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check() && auth()->user()->isManager()) {
            return redirect()->route(auth()->user()->must_change_password ? 'manager.password.change' : 'manager.dashboard');
        }

        return view('auth.manager-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }

        $manager = $request->user();
        if (! $manager->isManager()
            || strcasecmp((string) $manager->status, 'Active') !== 0
            || ! TodaOrganization::query()->whereKey($manager->organization_id)->where('status', 'Active')->exists()) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account is not authorized for the Manager Dashboard.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route($manager->must_change_password ? 'manager.password.change' : 'manager.dashboard');
    }

    public function showPasswordChange(): View|RedirectResponse
    {
        if (! auth()->user()->must_change_password) {
            return redirect()->route('manager.dashboard');
        }

        return view('auth.manager-password-change');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $manager = $request->user();
        if (! Hash::check($attributes['current_password'], $manager->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $manager->forceFill([
            'password' => Hash::make($attributes['password']),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('manager.dashboard')->with('status', 'Password changed successfully.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('manager.login');
    }
}
