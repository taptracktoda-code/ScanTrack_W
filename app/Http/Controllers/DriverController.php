<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use App\Mail\DriverAccountCreatedMail;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function index(): View
    {
        $drivers = Driver::with(['organization', 'qrCode'])->latest()->get();
        $organizations = TodaOrganization::orderBy('name')->get();
        $driverAccounts = User::whereIn('user_id', $drivers->map(fn (Driver $driver): string => 'DRIVER-' . strtoupper((string) $driver->id)))
            ->get()
            ->keyBy('user_id');

        $stats = [
            'active' => $drivers->where('status', 'Active')->count(),
            'in_service' => $drivers->where('status', 'Active')->whereNotNull('vehicle_number')->count(),
            'today' => $drivers->where('updated_at', '>=', now()->startOfDay())->count(),
            'suspended' => $drivers->where('status', 'Suspended')->count(),
        ];

        return view('drivers.index', compact('drivers', 'stats', 'organizations', 'driverAccounts'));
    }

    public function qrManagement(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');
        $qrCodes = QrCode::with('driver.organization')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('qr_token', 'like', "%{$search}%")
                        ->orWhereHas('driver', function ($driverQuery) use ($search): void {
                            $driverQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('vehicle_number', 'like', "%{$search}%")
                                ->orWhereHas('organization', fn ($organizationQuery) => $organizationQuery->where('name', 'like', "%{$search}%"));
                        });
                    });
            })
            ->when(in_array($status, ['Active', 'Inactive'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        return view('qr-management.index', compact('qrCodes', 'search', 'status'));
    }

    public function register(Request $request): RedirectResponse
    {
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
            'organization_id' => ['required', 'exists:toda_organizations,id'],
        ]);

        $temporaryPassword = Str::password(16, letters: true, numbers: true, symbols: true);
        $driver = DB::transaction(function () use ($attributes, $temporaryPassword): Driver {
            $name = trim(implode(' ', array_filter([$attributes['first_name'], $attributes['middle_name'] ?? null, $attributes['last_name']])));
            $driver = Driver::create([
                'name' => $name,
                'first_name' => $attributes['first_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'last_name' => $attributes['last_name'],
                'license_number' => $attributes['license_number'],
                'license_expiration_date' => $attributes['license_expiration_date'],
                'contact_number' => $attributes['contact_number'],
                'address' => $attributes['address'],
                'vehicle_number' => $attributes['plate_number'],
                'organization_id' => $attributes['organization_id'],
                'status' => 'Active',
            ]);

            Vehicle::create([
                'name' => $name,
                'plate_number' => $attributes['plate_number'],
                'vehicle_type' => $attributes['vehicle_type'],
                'status' => 'Active',
            ]);

            User::create([
                'name' => $name,
                'email' => $attributes['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => 'Driver',
                'user_id' => 'DRIVER-' . strtoupper((string) $driver->id),
                'license_number' => $driver->license_number,
                'contact' => $driver->contact_number,
                'address' => $driver->address,
                'status' => 'Active',
                'must_change_password' => true,
            ]);

            $this->issueQrCode($driver);

            return $driver;
        });

        try {
            Mail::to($attributes['email'])
                ->bcc(config('mail.from.address'))
                ->send(new DriverAccountCreatedMail($driver, $attributes['email'], $temporaryPassword));
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('drivers')->with('status', 'Driver registered successfully with an active QR code, but the email could not be sent. Please check the mail configuration or resend the credentials.');
        }

        return redirect()->route('drivers')->with('status', 'Driver registered successfully with an active QR code. Login credentials have been sent to the driver\'s email.');
    }

    public function resendCredentials(Driver $driver): RedirectResponse
    {
        $user = User::where('user_id', 'DRIVER-' . strtoupper((string) $driver->id))->firstOrFail();
        $temporaryPassword = Str::password(16, letters: true, numbers: true, symbols: true);
        $user->forceFill(['password' => Hash::make($temporaryPassword), 'must_change_password' => true])->save();

        try {
            Mail::to($user->email)
                ->bcc(config('mail.from.address'))
                ->send(new DriverAccountCreatedMail($driver, $user->email, $temporaryPassword));
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('drivers')->with('status', 'A new password was generated, but the email could not be sent. Please check the mail configuration.');
        }

        return redirect()->route('drivers')->with('status', 'A new temporary password was sent to the driver\'s email.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedDriver($request);

        if (!empty($validated['vehicle_number'])) {
            Vehicle::firstOrCreate(
                ['plate_number' => $validated['vehicle_number']],
                ['name' => $validated['vehicle_number'], 'status' => 'Active']
            );
        }

        $driver = Driver::create($validated);
        $this->issueQrCode($driver);

        return redirect()->route('drivers')->with('status', 'Driver added successfully.');
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validatedDriver($request));

        return redirect()->route('drivers')->with('status', 'Driver updated successfully.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $driver->delete();

        return redirect()->route('drivers')->with('status', 'Driver deleted successfully.');
    }

    public function qrImage(Request $request, Driver $driver)
    {
        abort_unless($driver->qrCode, 404);

        $result = (new Builder(
            writer: new SvgWriter(),
            data: $driver->qrCode->qr_token,
            size: 360,
            margin: 16,
        ))->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => $request->boolean('download')
                ? 'attachment; filename="driver-' . $driver->id . '-qr.svg"'
                : 'inline; filename="driver-' . $driver->id . '-qr.svg"',
        ]);
    }

    public function regenerateQr(Driver $driver): RedirectResponse
    {
        $this->issueQrCode($driver, replace: true);

        return redirect()->route('drivers')->with('status', 'The driver QR code was regenerated. The previous QR code is no longer active.');
    }

    public function activateQr(Driver $driver): RedirectResponse
    {
        $driver->qrCode()->update(['status' => 'Active']);

        return redirect()->route('drivers')->with('status', 'The driver QR code is active.');
    }

    public function deactivateQr(Driver $driver): RedirectResponse
    {
        $driver->qrCode()->update(['status' => 'Inactive']);

        return redirect()->route('drivers')->with('status', 'The driver QR code was deactivated.');
    }

    public function options()
    {
        return response()->json([
            'drivers' => Driver::select('id', 'name', 'contact_number')->latest()->get(),
        ]);
    }

    public function sendSms(Request $request, Driver $driver): RedirectResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = $request->input('message');
        $phone = $driver->contact_number;

        if (!filled($phone)) {
            return redirect()->route('drivers')->with('status', 'Driver contact number is missing.');
        }

        $smsSent = $this->sendSmsToPhone($phone, $message);

        return redirect()->route('drivers')->with('status', $smsSent ? 'SMS notification sent successfully.' : 'SMS was not sent. Please configure your SMS gateway in .env.');
    }

    private function sendSmsToPhone(string $phone, string $message): bool
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (!$sid || !$token || !$from) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(10)
                ->connectTimeout(5)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $phone,
                    'Body' => $message,
                ]);

            return $response->successful();
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function validatedDriver(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'license_number' => ['required', 'string', 'max:80'],
            'contact_number' => ['required', 'string', 'max:40'],
            'vehicle_number' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:Active,Suspended'],
        ]);
    }

    private function issueQrCode(Driver $driver, bool $replace = false): QrCode
    {
        if (!$replace && $driver->qrCode()->exists()) {
            return $driver->qrCode()->firstOrFail();
        }

        do {
            $token = 'TT-DRV-' . strtoupper(bin2hex(random_bytes(6)));
        } while (QrCode::where('qr_token', $token)->exists());

        return $driver->qrCode()->updateOrCreate(
            ['driver_id' => $driver->id],
            ['qr_token' => $token, 'status' => 'Active', 'generated_at' => now()]
        );
    }
}
