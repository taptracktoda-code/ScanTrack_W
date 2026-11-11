<?php

namespace App\Http\Controllers;

use App\Mail\ManagerAccountCreatedMail;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ManagerController extends Controller
{
    public function index(): View
    {
        return view('managers.index', [
            'managers' => User::query()->where('role', 'Manager')->with('organization')->withMax('terminalActivities', 'created_at')->latest()->paginate(20),
            'organizations' => TodaOrganization::query()->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'contact' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'integer', 'exists:toda_organizations,id'],
        ]);
        abort_unless(TodaOrganization::whereKey($attributes['organization_id'])->where('status', 'Active')->exists(), 422);

        $temporaryPassword = Str::password(16, letters: true, numbers: true, symbols: true);
        $name = trim(implode(' ', array_filter([$attributes['first_name'], $attributes['middle_name'] ?? null, $attributes['last_name']])));
        $manager = User::create([
            'name' => $name,
            'first_name' => $attributes['first_name'],
            'middle_name' => $attributes['middle_name'] ?? null,
            'last_name' => $attributes['last_name'],
            'email' => $attributes['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => 'Manager',
            'user_id' => 'MANAGER-' . Str::upper(Str::random(20)),
            'license_number' => '',
            'contact' => $attributes['contact'],
            'address' => '',
            'organization_id' => $attributes['organization_id'],
            'status' => 'Active',
            'must_change_password' => true,
        ]);

        $sent = $this->sendCredentials($manager, $temporaryPassword);
        TerminalActivity::create([
            'admin_id' => auth()->id(),
            'activity' => 'Manager account created',
            'description' => $manager->name . ' assigned to ' . $manager->organization?->name,
        ]);

        return redirect()->route('managers')->with('status', $sent
            ? 'Manager account created. Login credentials were sent to the manager email.'
            : 'Manager account created, but the credentials email could not be sent. Reset the password after checking mail configuration.');
    }

    public function update(Request $request, User $manager): RedirectResponse
    {
        $this->ensureManager($manager);
        $attributes = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,' . $manager->id],
            'contact' => ['required', 'string', 'max:40'],
            'organization_id' => ['required', 'integer', 'exists:toda_organizations,id'],
        ]);
        abort_unless(TodaOrganization::whereKey($attributes['organization_id'])->where('status', 'Active')->exists(), 422);

        $manager->update([
            ...$attributes,
            'name' => trim(implode(' ', array_filter([$attributes['first_name'], $attributes['middle_name'] ?? null, $attributes['last_name']]))),
        ]);
        TerminalActivity::create([
            'admin_id' => auth()->id(),
            'activity' => 'Manager account updated',
            'description' => $manager->name . ' profile or organization assignment changed',
        ]);

        return redirect()->route('managers')->with('status', 'Manager details updated.');
    }

    public function assignOrganization(Request $request, User $manager): JsonResponse
    {
        $this->ensureManager($manager);
        $attributes = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('toda_organizations', 'id')->where('status', 'Active')],
        ]);

        $manager->update(['organization_id' => $attributes['organization_id']]);
        TerminalActivity::create([
            'admin_id' => $request->user()->id,
            'activity' => 'Manager organization changed',
            'description' => $manager->name . ' assigned to ' . $manager->organization?->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Manager assigned to organization.',
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'organization_id' => $manager->organization_id,
                'organization' => $manager->organization?->name,
            ],
        ]);
    }

    public function destroy(User $manager): RedirectResponse
    {
        $this->ensureManager($manager);

        DB::transaction(function () use ($manager): void {
            $manager->tokens()->delete();
            $manager->expoPushTokens()->delete();
            $manager->delete();

            TerminalActivity::create([
                'admin_id' => auth()->id(),
                'activity' => 'Manager account deleted',
                'description' => $manager->name . ' (manager ID: ' . $manager->id . ') removed from ' . ($manager->organization?->name ?? 'no organization'),
            ]);
        });

        return redirect()->route('managers')->with('status', 'Manager account deleted.');
    }

    public function toggleStatus(User $manager): RedirectResponse
    {
        $this->ensureManager($manager);
        $manager->update(['status' => strcasecmp((string) $manager->status, 'Active') === 0 ? 'Inactive' : 'Active']);
        TerminalActivity::create([
            'admin_id' => auth()->id(),
            'activity' => 'Manager status changed',
            'description' => $manager->name . ' is now ' . $manager->status,
        ]);

        return redirect()->route('managers')->with('status', 'Manager account status updated.');
    }

    public function resetPassword(User $manager): RedirectResponse
    {
        $this->ensureManager($manager);
        $temporaryPassword = Str::password(16, letters: true, numbers: true, symbols: true);
        $manager->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();
        $sent = $this->sendCredentials($manager, $temporaryPassword);
        TerminalActivity::create([
            'admin_id' => auth()->id(),
            'activity' => 'Manager password reset',
            'description' => 'Temporary credentials reissued for ' . $manager->name,
        ]);

        return redirect()->route('managers')->with('status', $sent
            ? 'A new temporary password was sent to the manager email.'
            : 'The password was reset, but the email could not be sent. Check mail configuration before resetting again.');
    }

    private function sendCredentials(User $manager, string $temporaryPassword): bool
    {
        try {
            Mail::to($manager->email)->send(new ManagerAccountCreatedMail($manager, $temporaryPassword));
            return true;
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function ensureManager(User $manager): void
    {
        abort_unless(strcasecmp((string) $manager->role, 'Manager') === 0, 404);
    }
}
