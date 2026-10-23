<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TodaOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            $isActive = strcasecmp((string) $user->status, 'Active') === 0;
            $isManagerWithActiveOrganization = $user->isManager()
                && TodaOrganization::query()
                    ->whereKey($user->organization_id)
                    ->where('status', 'Active')
                    ->exists();

            if (! $isActive || (! $user->isSuperAdmin() && ! $isManagerWithActiveOrganization)) {
                Auth::logout();

                return back()
                    ->withErrors(['email' => 'This account is not authorized for the web application.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            if ($user->isManager()) {
                return redirect()->route($user->must_change_password ? 'manager.password.change' : 'manager.dashboard');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['email' => 'The provided credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function adminLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if (! $user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not authorized for the admin application.',
            ], 403);
        }

        if (strcasecmp((string) $user->status, 'Active') !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'This admin account is inactive.',
            ], 403);
        }

        $token = $user->createToken('tap-track-admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => User::ROLE_SUPER_ADMIN,
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
