<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\DriverIssueRequest;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ManagerDriverAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManagerPortalController extends Controller
{
    public function drivers(Request $request): View
    {
        $organization = $this->assignedOrganization($request->user());
        $drivers = Driver::query()
            ->where('organization_id', $organization->id)
            ->with('qrCode')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = trim((string) $request->query('search'));
                $query->where(fn ($driverQuery) => $driverQuery
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('license_number', 'like', "%{$term}%")
                    ->orWhere('vehicle_number', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('managers.portal.drivers', compact('organization', 'drivers'));
    }

    public function storeDriver(Request $request, ManagerDriverAccountService $driverAccounts): RedirectResponse
    {
        $manager = $request->user();
        $organization = $this->assignedOrganization($manager);
        $attributes = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'contact_number' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:1000'],
            'license_number' => ['required', 'string', 'max:80', 'unique:drivers,license_number'],
            'license_expiration_date' => ['required', 'date'],
            'plate_number' => ['required', 'string', 'max:80', 'unique:vehicles,plate_number'],
            'vehicle_type' => ['required', 'string', 'max:80'],
        ]);

        $result = $driverAccounts->create($attributes, $manager, $organization);
        $emailSent = $driverAccounts->sendCredentials(
            $result['driver'],
            $result['email'],
            $result['temporary_password']
        );
        $message = $emailSent
            ? 'Driver account created and activated. Login credentials were sent to the driver email.'
            : 'Driver account created and activated, but the credential email failed. Check mail configuration and send a secure password reset.';

        return redirect()->route('manager.drivers')->with('status', $message);
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $this->ensureOwnDriver($request->user(), $driver);
        $attributes = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'contact_number' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:1000'],
            'license_number' => ['required', 'string', 'max:80', Rule::unique('drivers', 'license_number')->ignore($driver->id)],
            'license_expiration_date' => ['required', 'date'],
        ]);

        $name = $this->fullName($attributes);
        DB::transaction(function () use ($driver, $attributes, $name): void {
            $driver->update([...$attributes, 'name' => $name]);
            User::query()
                ->where('user_id', 'DRIVER-'.strtoupper((string) $driver->id))
                ->whereRaw('LOWER(role) = ?', [strtolower(User::ROLE_DRIVER)])
                ->update([
                    'name' => $name,
                    'first_name' => $attributes['first_name'],
                    'middle_name' => $attributes['middle_name'] ?? null,
                    'last_name' => $attributes['last_name'],
                    'contact' => $attributes['contact_number'],
                    'address' => $attributes['address'],
                    'license_number' => $attributes['license_number'],
                ]);
        });

        return redirect()->route('manager.drivers')->with('status', 'Driver details updated.');
    }

    public function vehicles(Request $request): View
    {
        $organization = $this->assignedOrganization($request->user());
        $vehicles = Vehicle::query()
            ->where('organization_id', $organization->id)
            ->with('driver')
            ->orderBy('plate_number')
            ->paginate(15);

        return view('managers.portal.vehicles', compact('organization', 'vehicles'));
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $organization = $this->assignedOrganization($request->user());
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate_number' => ['required', 'string', 'max:80', 'unique:vehicles,plate_number'],
            'vehicle_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        Vehicle::create([...$attributes, 'organization_id' => $organization->id]);

        return redirect()->route('manager.vehicles')->with('status', 'Vehicle added.');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->ensureOwnVehicle($request->user(), $vehicle);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate_number' => ['required', 'string', 'max:80', Rule::unique('vehicles', 'plate_number')->ignore($vehicle->id)],
            'vehicle_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $previousPlate = $vehicle->plate_number;
        DB::transaction(function () use ($vehicle, $attributes, $previousPlate): void {
            $vehicle->update($attributes);
            if ($previousPlate !== $vehicle->plate_number) {
                Driver::query()
                    ->where('organization_id', $vehicle->organization_id)
                    ->where('vehicle_number', $previousPlate)
                    ->update(['vehicle_number' => $vehicle->plate_number]);
            }
        });

        return redirect()->route('manager.vehicles')->with('status', 'Vehicle updated.');
    }

    public function destroyVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->ensureOwnVehicle($request->user(), $vehicle);
        abort_if($vehicle->driver()->exists(), 409, 'Unassign the vehicle from its driver before deleting it.');
        $vehicle->delete();

        return redirect()->route('manager.vehicles')->with('status', 'Vehicle deleted.');
    }

    public function qrManagement(Request $request): View
    {
        $organization = $this->assignedOrganization($request->user());
        $qrCodes = QrCode::query()
            ->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('driver')
            ->latest()
            ->paginate(15);

        return view('managers.portal.qr-management', compact('organization', 'qrCodes'));
    }

    public function regenerateQr(Request $request, Driver $driver): RedirectResponse
    {
        $this->ensureOwnDriver($request->user(), $driver);
        abort_unless(strcasecmp((string) $driver->status, 'Active') === 0, 409);

        do {
            $token = bin2hex(random_bytes(16));
        } while (QrCode::query()->where('qr_token', $token)->exists());

        $driver->qrCode()->updateOrCreate(
            ['driver_id' => $driver->id],
            ['qr_token' => $token, 'status' => 'Active', 'generated_at' => now()]
        );

        return redirect()->route('manager.qr.management')->with('status', 'Driver QR credential regenerated.');
    }

    public function setQrStatus(Request $request, Driver $driver, string $status): RedirectResponse
    {
        $this->ensureOwnDriver($request->user(), $driver);
        abort_unless($driver->qrCode()->exists(), 404);
        abort_unless(strcasecmp((string) $driver->status, 'Active') === 0, 409);

        $driver->qrCode()->update(['status' => $status === 'activate' ? 'Active' : 'Inactive']);

        return redirect()->route('manager.qr.management')->with('status', 'QR credential status updated.');
    }

    public function attendance(Request $request): View
    {
        $organization = $this->assignedOrganization($request->user());
        $filters = $request->validate([
            'driver_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')->where('organization_id', $organization->id)],
            'attendance_type' => ['nullable', 'in:ENTRY,EXIT'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $records = DriverLog::query()
            ->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('driver')
            ->when(isset($filters['driver_id']), fn ($query) => $query->where('driver_id', $filters['driver_id']))
            ->when(isset($filters['attendance_type']), fn ($query) => $query->where('attendance_type', $filters['attendance_type']))
            ->when(isset($filters['start_date']), fn ($query) => $query->whereRaw('COALESCE(scanned_at, driver_logs.created_at) >= ?', [$filters['start_date'].' 00:00:00']))
            ->when(isset($filters['end_date']), fn ($query) => $query->whereRaw('COALESCE(scanned_at, driver_logs.created_at) <= ?', [$filters['end_date'].' 23:59:59']))
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
        $drivers = Driver::query()->where('organization_id', $organization->id)->orderBy('name')->get(['id', 'name']);

        return view('managers.portal.attendance', compact('organization', 'records', 'drivers', 'filters'));
    }

    public function reports(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $organization = $this->assignedOrganization($request->user());
        $reports = DriverIssueRequest::with('driver')->where('organization_id', $organization->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $filters['status']))
            ->when($request->filled('date'), function ($query) use ($filters) {
                [$start, $end] = \App\Services\PhilippineDay::bounds($filters['date']);
                $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
            })
            ->when($request->filled('search'), function ($query) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $query->where(fn ($q) => $q->where('explanation', 'like', $term)
                    ->orWhereHas('driver', fn ($driver) => $driver->where('name', 'like', $term)));
            })->latest('id')->paginate(15)->withQueryString();
        $drivers = Driver::query()->where('organization_id', $organization->id);
        $vehicles = Vehicle::query()->where('organization_id', $organization->id);
        $logs = DriverLog::query()->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id));

        return view('managers.portal.reports', [
            'organization' => $organization,
            'reports' => $reports,
            'totalDrivers' => (clone $drivers)->count(),
            'activeDrivers' => (clone $drivers)->where('status', 'Active')->count(),
            'totalVehicles' => (clone $vehicles)->count(),
            'todayEntries' => (clone $logs)->where('attendance_type', 'ENTRY')->whereDate('scanned_at', today())->count(),
            'todayExits' => (clone $logs)->where('attendance_type', 'EXIT')->whereDate('scanned_at', today())->count(),
        ]);
    }

    public function exportReports(Request $request)
    {
        $organization = $this->assignedOrganization($request->user());
        $drivers = Driver::query()
            ->where('organization_id', $organization->id)
            ->withCount([
                'logs as total_entries' => fn ($query) => $query->where('attendance_type', 'ENTRY'),
                'logs as total_exits' => fn ($query) => $query->where('attendance_type', 'EXIT'),
            ])
            ->withMax('logs', 'scanned_at')
            ->orderBy('name')
            ->get();
        $logs = DriverLog::query()->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id));
        $summary = [
            'total_drivers' => Driver::query()->where('organization_id', $organization->id)->count(),
            'active_drivers' => Driver::query()->where('organization_id', $organization->id)->where('status', 'Active')->count(),
            'total_vehicles' => Vehicle::query()->where('organization_id', $organization->id)->count(),
            'today_entries' => (clone $logs)->where('attendance_type', 'ENTRY')->whereDate('scanned_at', today())->count(),
            'today_exits' => (clone $logs)->where('attendance_type', 'EXIT')->whereDate('scanned_at', today())->count(),
        ];

        return response()->streamDownload(function () use ($organization, $drivers, $summary): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Report Date',
                'Organization',
                'Total Drivers',
                'Active Drivers',
                'Total Vehicles',
                'Entries Today',
                'Exits Today',
                'Driver ID',
                'Driver Name',
                'License Number',
                'Contact Number',
                'Plate Number',
                'Driver Status',
                'Total Entries',
                'Total Exits',
                'Last Scan At',
            ]);

            if ($drivers->isEmpty()) {
                fputcsv($handle, [
                    now()->toDateString(),
                    $this->csvValue($organization->name),
                    $summary['total_drivers'],
                    $summary['active_drivers'],
                    $summary['total_vehicles'],
                    $summary['today_entries'],
                    $summary['today_exits'],
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ]);
            }

            foreach ($drivers as $driver) {
                fputcsv($handle, [
                    now()->toDateString(),
                    $this->csvValue($organization->name),
                    $summary['total_drivers'],
                    $summary['active_drivers'],
                    $summary['total_vehicles'],
                    $summary['today_entries'],
                    $summary['today_exits'],
                    $driver->id,
                    $this->csvValue($driver->name),
                    $this->csvValue($driver->license_number),
                    $this->csvValue($driver->contact_number),
                    $this->csvValue($driver->vehicle_number),
                    $this->csvValue($driver->status),
                    $driver->total_entries,
                    $driver->total_exits,
                    optional($driver->logs_max_scanned_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'organization-report-'.$organization->id.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function organization(Request $request): View
    {
        return view('managers.portal.organization', [
            'organization' => $this->assignedOrganization($request->user()),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('managers.portal.profile', ['manager' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $manager = $request->user();
        $attributes = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($manager->id)],
            'contact' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_picture' => ['sometimes', 'boolean'],
        ]);
        $previousPicture = $manager->profile_picture;
        unset($attributes['profile_picture'], $attributes['remove_picture']);
        $path = null;
        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('profile-pictures', 'public');
            if (! $path) return back()->withErrors(['profile_picture' => 'The profile photo could not be saved. Please try again.'])->withInput();
            $attributes['profile_picture'] = $path;
        } elseif ($request->boolean('remove_picture')) {
            $attributes['profile_picture'] = null;
        }
        try {
            $manager->forceFill([...$attributes, 'name' => $this->fullName($attributes)])->save();
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('public')->delete($path);
            report($exception);
            return back()->withErrors(['profile' => 'Your profile could not be saved. Please try again.'])->withInput();
        }
        if (array_key_exists('profile_picture', $attributes) && $previousPicture && $previousPicture !== $attributes['profile_picture']) {
            Storage::disk('public')->delete($previousPicture);
        }

        return redirect()->route('manager.profile')->with('status', 'Profile updated.');
    }

    private function assignedOrganization(User $manager): TodaOrganization
    {
        return TodaOrganization::query()
            ->whereKey($manager->organization_id)
            ->where('status', 'Active')
            ->firstOrFail();
    }

    private function ensureOwnDriver(User $manager, Driver $driver): void
    {
        abort_unless((int) $driver->organization_id === (int) $this->assignedOrganization($manager)->id, 404);
    }

    private function ensureOwnVehicle(User $manager, Vehicle $vehicle): void
    {
        abort_unless((int) $vehicle->organization_id === (int) $this->assignedOrganization($manager)->id, 404);
    }

    private function fullName(array $attributes): string
    {
        return trim(implode(' ', array_filter([
            $attributes['first_name'],
            $attributes['middle_name'] ?? null,
            $attributes['last_name'],
        ])));
    }

    private function csvValue(?string $value): string
    {
        $value = $value ?? '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
