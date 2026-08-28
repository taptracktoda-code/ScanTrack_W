<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function index(): View
    {
        $drivers = Driver::latest()->get();

        $stats = [
            'active' => $drivers->where('status', 'Active')->count(),
            'in_service' => $drivers->where('status', 'Active')->whereNotNull('vehicle_number')->count(),
            'today' => $drivers->where('updated_at', '>=', now()->startOfDay())->count(),
            'suspended' => $drivers->where('status', 'Suspended')->count(),
        ];

        return view('drivers.index', compact('drivers', 'stats'));
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

        Driver::create($validated);

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
}
