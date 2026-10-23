<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TodaOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'user' => auth()->user(),
        ]);
    }

    public function profile(): View
    {
        return view('settings.profile', [
            'user' => auth()->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,' . auth()->id()],
            'unit' => ['required', 'in:Main unit,Unit 1,Unit 2'],
            'contact' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth()->user();
        $data = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'unit' => $request->input('unit'),
            'contact' => $request->input('contact') ?? '',
            'address' => $request->input('address') ?? '',
        ];

        $previousPicture = $user->profile_picture;
        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('profile-pictures', 'public');

            if (! $path) {
                return back()->withErrors(['profile_picture' => 'The profile picture could not be saved. Please try again.'])->withInput();
            }

            $data['profile_picture'] = $path;
        }

        $user->forceFill($data)->save();

        if (isset($data['profile_picture']) && $previousPicture && $previousPicture !== $data['profile_picture']) {
            Storage::disk('public')->delete($previousPicture);
        }

        return redirect()->route('settings.profile')->with('status', 'Profile updated successfully.');
    }

    public function editTodaOrganizations(): View
    {
        return view('settings.toda-organizations', [
            'value' => (int) Setting::getValue('toda_organizations', 15),
            'toda_name' => Setting::getValue('toda_name', ''),
            'barangay_travel_area' => Setting::getValue('barangay_travel_area', ''),
            'organizations' => TodaOrganization::withCount(['managers', 'drivers', 'vehicles'])->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function storeTodaOrganization(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barangay_travel_area' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        TodaOrganization::create([
            'name' => $request->input('name'),
            'barangay_travel_area' => $request->input('barangay_travel_area'),
            'address' => $request->input('address'),
            'contact_number' => $request->input('contact_number'),
            'email' => $request->input('email'),
            'status' => 'Active',
        ]);

        return redirect()->route('settings.toda_organizations')
            ->with('status', 'Organization added successfully.');
    }

    public function updateTodaOrganization(Request $request, TodaOrganization $organization): RedirectResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barangay_travel_area' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $organization->update($attributes);

        return redirect()->route('settings.toda_organizations')->with('status', 'Organization details updated.');
    }

    public function toggleTodaOrganization(TodaOrganization $organization): RedirectResponse
    {
        $organization->update([
            'status' => strcasecmp((string) $organization->status, 'Active') === 0 ? 'Inactive' : 'Active',
        ]);

        return redirect()->route('settings.toda_organizations')->with('status', 'Organization status updated.');
    }
}
