<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\NfcCard;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DriverApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'phone_number' => ['required', 'string', 'max:40'],
            'license_number' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'unit' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $attributes = $validator->validated();

        $user = DB::transaction(function () use ($attributes): User {
            $driver = Driver::create([
                'name' => $attributes['full_name'],
                'license_number' => $attributes['license_number'],
                'contact_number' => $attributes['phone_number'],
                'vehicle_number' => null,
                'status' => 'Active',
            ]);

            return User::create([
                'name' => $attributes['full_name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'role' => 'Driver',
                'unit' => $attributes['unit'] ?? null,
                'user_id' => 'DRIVER-' . strtoupper((string) $driver->id),
                'license_number' => $driver->license_number,
                'contact' => $attributes['phone_number'],
                'address' => $attributes['address'] ?? '',
                'status' => 'Active',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Driver account registered successfully.',
            'data' => ['driver' => $this->driverData($user)],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if (strcasecmp((string) $user->role, 'driver') !== 0 || ! $this->driverExists($user)) {
            return response()->json(['success' => false, 'message' => 'This account is not authorized for the driver application.'], 403);
        }

        if (strcasecmp((string) $user->status, 'Active') !== 0) {
            return response()->json(['success' => false, 'message' => 'This driver account is inactive.'], 403);
        }

        $token = $user->createToken('tap-track-driver')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => ['token' => $token, 'driver' => $this->driverData($user)],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->success('Driver profile retrieved successfully.', $this->driverData($request->user()));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $today = $this->attendanceForDate($driver, Carbon::today());

        return $this->success('Dashboard retrieved successfully.', [
            'driver' => $this->driverData($request->user()),
            'attendance' => $today,
            'total_trips' => 0,
            'recent_records' => $this->records($driver, 4),
        ]);
    }

    public function todayAttendance(Request $request): JsonResponse
    {
        return $this->attendanceByDateValue($request, Carbon::today());
    }

    public function attendanceByDate(Request $request, string $date): JsonResponse
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Invalid date.'], 422);
        }

        return $this->attendanceByDateValue($request, $parsed);
    }

    public function attendanceHistory(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $limit = min(max($request->integer('limit', 10), 1), 100);
        return $this->success('Attendance history retrieved successfully.', ['history' => $this->records($driver, $limit)]);
    }

    public function monthlyAttendance(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $month = Carbon::createFromDate($request->integer('year', now()->year), $request->integer('month', now()->month), 1);
        $logs = DriverLog::where('driver_id', $driver->id)->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->get();
        return $this->success('Monthly attendance retrieved successfully.', $this->summary($logs));
    }

    public function attendanceSummary(Request $request): JsonResponse
    {
        $logs = DriverLog::where('driver_id', $this->driver($request->user())->id)->get();
        return $this->success('Attendance summary retrieved successfully.', $this->summary($logs));
    }

    public function emptyTrips(): JsonResponse
    {
        return $this->success('No trip records are available.', []);
    }

    public function emptyTripSummary(): JsonResponse
    {
        return $this->success('Trip summary retrieved successfully.', ['total_trips' => 0, 'completed_trips' => 0, 'cancelled_trips' => 0]);
    }

    public function emptySchedule(): JsonResponse
    {
        return $this->success('No schedules are available.', []);
    }

    private function attendanceByDateValue(Request $request, Carbon $date): JsonResponse
    {
        $driver = $this->driver($request->user());
        return $this->success('Attendance retrieved successfully.', $this->attendanceForDate($driver, $date));
    }

    private function driver(User $user): Driver
    {
        return Driver::findOrFail($this->driverId($user));
    }

    private function driverExists(User $user): bool
    {
        return (bool) $this->driverId($user);
    }

    private function driverId(User $user): ?int
    {
        $userId = (string) $user->user_id;

        if (! str_starts_with($userId, 'DRIVER-')) {
            return null;
        }

        $driverId = substr($userId, 7);

        return ctype_digit($driverId) ? (int) $driverId : null;
    }

    private function driverData(User $user): array
    {
        $driver = $this->driver($user);
        $card = Schema::hasColumn('nfc_cards', 'driver_id')
            ? NfcCard::where('driver_id', $driver->id)->first()
            : null;
        return [
            'id' => (string) $driver->id,
            'driver_id' => (string) $driver->id,
            'name' => $driver->name,
            'email' => $user->email,
            'phone' => $driver->contact_number,
            'vehicle_number' => $driver->vehicle_number,
            'nfc_card_status' => $card ? 'registered' : 'unregistered',
            'account_status' => strtolower((string) ($driver->status ?: $user->status)),
        ];
    }

    private function attendanceForDate(Driver $driver, Carbon $date): array
    {
        $logs = DriverLog::where('driver_id', $driver->id)->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->orderBy('created_at')->get();
        $entry = $logs->firstWhere('attendance_type', 'ENTRY');
        $exit = $logs->firstWhere('attendance_type', 'EXIT');
        return ['date' => $date->toDateString(), 'entry_time' => $entry?->created_at?->toIso8601String(), 'exit_time' => $exit?->created_at?->toIso8601String(), 'status' => $entry && ! $exit ? 'active' : ($exit ? 'completed' : 'not_checked_in'), 'history' => $this->records($driver, 10, $date)];
    }

    private function records(Driver $driver, int $limit, ?Carbon $date = null): array
    {
        $query = DriverLog::where('driver_id', $driver->id)->latest('created_at');
        if ($date) $query->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        return $query->limit($limit)->get()->map(fn (DriverLog $log) => ['id' => $log->id, 'date' => $log->created_at?->toDateString(), 'entry_time' => $log->attendance_type === 'ENTRY' ? $log->created_at?->toIso8601String() : null, 'exit_time' => $log->attendance_type === 'EXIT' ? $log->created_at?->toIso8601String() : null, 'entry_verified' => true, 'exit_verified' => true])->values()->all();
    }

    private function summary($logs): array
    {
        $present = $logs->where('attendance_type', 'ENTRY')->groupBy(fn ($log) => $log->created_at?->toDateString())->count();
        return ['present_days' => $present, 'absent_days' => 0, 'late_records' => 0, 'total_work_hours' => 0];
    }

    private function success(string $message, mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
