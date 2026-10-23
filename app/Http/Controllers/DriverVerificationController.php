<?php

namespace App\Http\Controllers;

use App\Mail\DriverAccountCreatedMail;
use App\Mail\DriverRegistrationRejectedMail;
use App\Models\Driver;
use App\Models\QrCode;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DriverVerificationController extends Controller
{
    public function index(): View
    {
        $drivers = Driver::query()
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->with(['organization', 'vehicle'])
            ->latest()
            ->paginate(20);
        $accounts = User::query()
            ->whereIn('user_id', $drivers->getCollection()->map(fn (Driver $driver): string => 'DRIVER-' . strtoupper((string) $driver->id)))->get()
            ->keyBy('user_id');

        $organizations = TodaOrganization::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('drivers.verification', compact('drivers', 'accounts', 'organizations'));
    }

    public function assign(Request $request, Driver $driver): RedirectResponse
    {
        abort_unless(strcasecmp((string) $driver->status, 'Pending') === 0, 409);

        $attributes = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('toda_organizations', 'id')->where('status', 'Active')],
            'plate_number' => ['required', 'string', 'max:80', 'unique:vehicles,plate_number'],
            'vehicle_type' => ['required', 'string', 'max:80'],
        ]);

        DB::transaction(function () use ($attributes, $driver): void {
            $organization = TodaOrganization::query()->findOrFail($attributes['organization_id']);
            $driver->forceFill([
                'organization_id' => $organization->id,
                'vehicle_number' => $attributes['plate_number'],
            ])->save();

            Vehicle::create([
                'name' => $driver->name,
                'plate_number' => $attributes['plate_number'],
                'vehicle_type' => $attributes['vehicle_type'],
                'organization_id' => $organization->id,
                'status' => 'Pending',
            ]);
        });

        return redirect()->route('drivers.verification')->with('status', 'Organization and vehicle assigned. The driver is ready for verification.');
    }

    public function approve(Driver $driver): RedirectResponse
    {
        if (strcasecmp((string) $driver->status, 'Pending') !== 0) {
            return redirect()->route('drivers.verification')->withErrors([
                'driver' => 'This driver is no longer pending verification. Refresh the page to see the latest status.',
            ]);
        }

        if (! $driver->first_name || ! $driver->last_name || ! $driver->license_number || ! $driver->contact_number || ! $driver->vehicle_number || strcasecmp((string) $driver->organization?->status, 'Active') !== 0) {
            return back()->withErrors(['driver' => 'Complete the driver, vehicle, and active organization information before approval.']);
        }

        $account = User::query()->where('user_id', 'DRIVER-' . strtoupper((string) $driver->id))->firstOrFail();
        $temporaryPassword = Str::password(18, letters: true, numbers: true, symbols: true);

        DB::transaction(function () use ($driver, $account, $temporaryPassword): void {
            $driver->forceFill([
                'status' => 'Active',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ])->save();
            $account->forceFill([
                'password' => Hash::make($temporaryPassword),
                'status' => 'Active',
                'must_change_password' => true,
            ])->save();
            Vehicle::query()->where('plate_number', $driver->vehicle_number)->update(['status' => 'Active']);
            $this->activateQrCode($driver);
            TerminalActivity::create([
                'admin_id' => auth()->id(),
                'driver_id' => $driver->id,
                'activity' => 'Driver approved',
                'description' => 'Driver account approved and activated',
            ]);
        });

        try {
            Mail::to($account->email)->send(new DriverAccountCreatedMail($driver, $account->email, $temporaryPassword));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('drivers.verification')->with('status', 'Driver approved, but the credential email failed. Use the secure password reset action after checking mail configuration.');
        }

        return redirect()->route('drivers.verification')->with('status', 'Driver approved. Login credentials were sent to the driver email.');
    }

    public function reject(Request $request, Driver $driver): RedirectResponse
    {
        abort_unless(strcasecmp((string) $driver->status, 'Pending') === 0, 409);
        $attributes = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $account = User::query()->where('user_id', 'DRIVER-' . strtoupper((string) $driver->id))->first();

        DB::transaction(function () use ($driver, $account, $attributes): void {
            $driver->forceFill([
                'status' => 'Rejected',
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                'rejection_reason' => $attributes['rejection_reason'],
            ])->save();
            $account?->forceFill(['status' => 'Rejected'])->save();
            $driver->qrCode()?->update(['status' => 'Inactive']);
            Vehicle::query()->where('plate_number', $driver->vehicle_number)->update(['status' => 'Inactive']);
            TerminalActivity::create([
                'admin_id' => auth()->id(),
                'driver_id' => $driver->id,
                'activity' => 'Driver rejected',
                'description' => $attributes['rejection_reason'],
            ]);
        });

        if ($account) {
            try {
                Mail::to($account->email)->send(new DriverRegistrationRejectedMail($driver, $attributes['rejection_reason']));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('drivers.verification')->with('status', 'Driver registration rejected and the decision was recorded.');
    }

    private function activateQrCode(Driver $driver): QrCode
    {
        $qrCode = $driver->qrCode;
        if ($qrCode) {
            $qrCode->update(['status' => 'Active']);
            return $qrCode;
        }

        do {
            $token = bin2hex(random_bytes(16));
        } while (QrCode::query()->where('qr_token', $token)->exists());

        return QrCode::create([
            'driver_id' => $driver->id,
            'qr_token' => $token,
            'status' => 'Active',
            'generated_at' => now(),
        ]);
    }
}