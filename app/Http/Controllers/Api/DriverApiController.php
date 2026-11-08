<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationDestination;
use App\Models\OrganizationEvent;
use App\Models\OrganizationQueueEntry;
use App\Models\QrCode;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use App\Services\DriverQueueService;
use App\Services\MobileNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DriverApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identity = trim($credentials['email']);
        $normalizedIdentity = strtolower($identity);
        $user = User::query()
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(email) = ?', [$normalizedIdentity])
                ->orWhereRaw('LOWER(user_id) = ?', [$normalizedIdentity]))
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials. If you recently applied, wait for Driver Verification approval and check your email for login credentials.',
            ], 401);
        }

        $driver = $this->driverExists($user) ? Driver::with('organization')->find($this->driverId($user)) : null;
        if (! $user->isDriver() || ! $driver) {
            return response()->json(['success' => false, 'message' => 'This account is not authorized for the driver application.'], 403);
        }

        if (strcasecmp((string) $user->status, 'Active') !== 0) {
            return response()->json(['success' => false, 'message' => 'This driver account is inactive.'], 403);
        }
        if ($driver->organization && strcasecmp((string) $driver->organization->status, 'Active') !== 0) {
            return response()->json(['success' => false, 'message' => 'The assigned organization is inactive.'], 403);
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

    public function changePassword(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);
        $user = $request->user();

        if (! Hash::check($attributes['current_password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'The current password is incorrect.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($attributes['password']),
            'must_change_password' => false,
        ])->save();

        return $this->success('Password changed successfully.', ['must_change_password' => false]);
    }

    public function updateShiftStatus(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'shift_status' => ['required', 'string', 'in:Available,On Break,Off Duty'],
        ]);

        $user = $request->user();
        if (! $user->isDriver() || ! $this->driverExists($user)) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not authorized for the driver application.',
            ], 403);
        }

        $user->update(['shift_status' => $attributes['shift_status']]);

        return $this->success('Shift status updated successfully.', [
            'shift_status' => $user->shift_status,
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->success('Driver profile retrieved successfully.', $this->driverData($request->user()));
    }

    public function uploadProfileAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('profile-pictures', 'public');
        if (! $path) {
            return response()->json(['success' => false, 'message' => 'The profile picture could not be saved. Please try again.'], 500);
        }

        $previousPicture = $user->profile_picture;
        $user->forceFill(['profile_picture' => $path])->save();

        if ($previousPicture && $previousPicture !== $path) {
            Storage::disk('public')->delete($previousPicture);
        }

        $avatarUrl = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully.',
            'data' => ['avatar' => $avatarUrl, 'avatar_url' => $avatarUrl],
        ]);
    }

    public function removeProfileAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $previousPicture = $user->profile_picture;
        $user->forceFill(['profile_picture' => null])->save();

        if ($previousPicture) {
            Storage::disk('public')->delete($previousPicture);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile picture removed successfully.',
            'data' => ['avatar' => null, 'avatar_url' => null],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $today = $this->attendanceForDate($driver, Carbon::today());

        return $this->success('Dashboard retrieved successfully.', [
            'driver' => $this->driverData($request->user()),
            'attendance' => $today,
            'queue' => app(DriverQueueService::class)->snapshot($driver),
            'total_trips' => Trip::where('driver_id', $driver->id)->count(),
            'today_completed_trips' => $this->completedToday($driver->id),
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

    public function todayTrips(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $trips = Trip::query()
            ->where('driver_id', $driver->id)
            ->whereDate('departure_time', Carbon::today())
            ->latest('departure_time')
            ->get()
            ->map(fn (Trip $trip): array => $this->tripData($trip))
            ->values();

        return $this->success('Today trips retrieved successfully.', ['trips' => $trips]);
    }

    public function tripDestinations(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $destinations = OrganizationDestination::query()
            ->where('organization_id', $driver->organization_id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->success('Available trip destinations retrieved successfully.', [
            'destinations' => $destinations,
        ]);
    }

    public function tripHistory(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        $limit = min(max($request->integer('limit', 20), 1), 100);
        $trips = Trip::query()
            ->where('driver_id', $driver->id)
            ->latest('departure_time')
            ->limit($limit)
            ->get()
            ->map(fn (Trip $trip): array => $this->tripData($trip))
            ->values();

        return $this->success('Trip history retrieved successfully.', ['trips' => $trips]);
    }

    public function activeTrip(Request $request): JsonResponse
    {
        $trip = Trip::query()
            ->where('driver_id', $this->driver($request->user())->id)
            ->where('status', 'in_progress')
            ->latest('departure_time')
            ->first();

        return $this->success('Active trip retrieved successfully.', $trip ? $this->tripData($trip) : null);
    }

    public function tripDetails(Request $request, string $tripId): JsonResponse
    {
        $trip = Trip::query()
            ->where('driver_id', $this->driver($request->user())->id)
            ->where('trip_id', $tripId)
            ->firstOrFail();

        return $this->success('Trip details retrieved successfully.', $this->tripData($trip));
    }

    public function startTrip(Request $request, MobileNotificationService $notifications): JsonResponse
    {
        $attributes = $request->validate([
            'destination_id' => ['required', 'integer'],
            'passenger_count' => ['required', 'integer', 'min:1'],
        ]);
        $driver = $this->driver($request->user());
        $destination = OrganizationDestination::query()
            ->whereKey($attributes['destination_id'])
            ->where('organization_id', $driver->organization_id)
            ->where('status', 'Active')
            ->first();

        if (! $destination) {
            throw ValidationException::withMessages([
                'destination_id' => ['Choose an active destination configured for your TODA.'],
            ]);
        }
        $today = Carbon::today();
        $latestScan = DriverLog::query()
            ->where('driver_id', $driver->id)
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $latestScan || $latestScan->attendance_type !== 'ENTRY' || $latestScan->scan_method !== 'QR') {
            throw ValidationException::withMessages([
                'qr_scan' => ['Scan your driver QR code with a manager at the terminal before starting a trip.'],
            ]);
        }

        $trip = DB::transaction(function () use ($driver, $attributes, $destination): Trip {
            TodaOrganization::whereKey($driver->organization_id)->lockForUpdate()->firstOrFail();
            Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if (Trip::query()->where('driver_id', $driver->id)->where('status', 'in_progress')->exists()) {
                throw ValidationException::withMessages([
                    'trip' => ['Complete your active trip before starting another one.'],
                ]);
            }

            $entry = OrganizationQueueEntry::where('organization_id', $driver->organization_id)
                ->where('driver_id', $driver->id)->whereIn('status', ['WAITING', 'SERVING'])
                ->lockForUpdate()->first();
            if (! $entry) {
                throw ValidationException::withMessages(['queue' => ['Have your manager scan your QR code to join the queue.']]);
            }
            $next = OrganizationQueueEntry::where('organization_id', $driver->organization_id)
                ->where('status', 'WAITING')->orderBy('queue_number')->orderBy('id')->first();
            if ($entry->status === 'WAITING' && $next?->id !== $entry->id) {
                throw ValidationException::withMessages(['queue' => ['Wait until you are next in line before starting a trip.']]);
            }
            // Recheck attendance under the same lock as scanning and queue transitions.
            $latestScan = DriverLog::where('driver_id', $driver->id)->whereDate('created_at', today())
                ->latest('created_at')->latest('id')->first();
            if ($latestScan?->attendance_type !== 'ENTRY' || $latestScan?->scan_method !== 'QR') {
                throw ValidationException::withMessages(['qr_scan' => ['A current manager QR entry scan is required.']]);
            }
            $entry->update(['status' => 'SERVING', 'served_at' => now()]);

            return Trip::create([
                'trip_id' => 'TRIP-'.strtoupper((string) Str::ulid()),
                'driver_id' => $driver->id,
                'destination_id' => $destination->id,
                'destination' => $destination->name,
                'passenger_count' => $attributes['passenger_count'],
                'status' => 'in_progress',
                'departure_time' => now(),
            ]);
        });

        $notifications->notifyOrganizationManagers(
            (int) $driver->organization_id,
            'Driver trip started',
            "{$driver->name} started a trip to {$trip->destination}.",
            'trip',
            ['screen' => 'history', 'driver_id' => $driver->id, 'trip_id' => $trip->trip_id],
        );

        return response()->json([
            'success' => true,
            'message' => 'Trip started successfully.',
            'data' => $this->tripData($trip),
        ], 201);
    }

    public function completeTrip(Request $request, string $tripId, MobileNotificationService $notifications): JsonResponse
    {
        $driver = $this->driver($request->user());
        $trip = DB::transaction(function () use ($driver, $tripId): Trip {
            TodaOrganization::whereKey($driver->organization_id)->lockForUpdate()->firstOrFail();
            Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $trip = Trip::query()
                ->where('driver_id', $driver->id)
                ->where('trip_id', $tripId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($trip->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'trip' => ['Only an active trip can be completed.'],
                ]);
            }

            $trip->update([
                'status' => 'completed',
                'arrival_time' => now(),
            ]);

            OrganizationQueueEntry::where('organization_id', $driver->organization_id)
                ->where('driver_id', $driver->id)->where('status', 'SERVING')
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);

            return $trip->refresh();
        });

        $notifications->notifyOrganizationManagers(
            (int) $driver->organization_id,
            'Driver trip completed',
            "{$driver->name} completed a trip to {$trip->destination}.",
            'trip',
            ['screen' => 'history', 'driver_id' => $driver->id, 'trip_id' => $trip->trip_id],
        );

        return $this->success('Trip completed successfully.', $this->tripData($trip));
    }

    public function tripSummary(Request $request): JsonResponse
    {
        $trips = Trip::query()->where('driver_id', $this->driver($request->user())->id);

        return $this->success('Trip summary retrieved successfully.', [
            'total_trips' => (clone $trips)->count(),
            'today_completed_trips' => $this->completedToday($this->driver($request->user())->id),
            'completed_trips' => (clone $trips)->where('status', 'completed')->count(),
            'cancelled_trips' => (clone $trips)->where('status', 'cancelled')->count(),
            'in_progress_trips' => (clone $trips)->where('status', 'in_progress')->count(),
        ]);
    }

    private function completedToday(int $driverId): int
    {
        [$start, $end] = \App\Services\PhilippineDay::bounds();
        return Trip::where('driver_id', $driverId)->where('status', 'completed')
            ->where('arrival_time', '>=', $start)->where('arrival_time', '<', $end)->count();
    }

    private function tripData(Trip $trip): array
    {
        return [
            'id' => (string) $trip->id,
            'trip_id' => $trip->trip_id,
            'driver_id' => (string) $trip->driver_id,
            'destination_id' => $trip->destination_id,
            'destination' => $trip->destination,
            'passenger_count' => $trip->passenger_count,
            'status' => $trip->status,
            'departure_time' => $trip->departure_time?->toIso8601String(),
            'arrival_time' => $trip->arrival_time?->toIso8601String(),
            'date' => $trip->departure_time?->toDateString(),
            'created_at' => $trip->created_at?->toIso8601String(),
            'updated_at' => $trip->updated_at?->toIso8601String(),
        ];
    }

    public function upcomingSchedule(Request $request): JsonResponse
    {
        $driver = $this->driver($request->user());
        if (! $driver->organization_id) {
            return $this->success('Upcoming schedules retrieved successfully.', []);
        }

        $events = OrganizationEvent::query()
            ->where('organization_id', $driver->organization_id)
            ->where(function ($query): void {
                $query->whereDate('event_date', '>', Carbon::today())
                    ->orWhere(function ($todayQuery): void {
                        $todayQuery->whereDate('event_date', Carbon::today())
                            ->where('end_time', '>=', now()->format('H:i:s'));
                    });
            })
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return $this->success('Upcoming schedules retrieved successfully.', $events);
    }

    public function scheduleByDate(Request $request, string $date): JsonResponse
    {
        try {
            $parsedDate = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Invalid date.'], 422);
        }

        if ($parsedDate->toDateString() !== $date) {
            return response()->json(['success' => false, 'message' => 'Invalid date.'], 422);
        }

        $driver = $this->driver($request->user());
        $events = $driver->organization_id
            ? OrganizationEvent::query()
                ->where('organization_id', $driver->organization_id)
                ->whereDate('event_date', $parsedDate)
                ->orderBy('start_time')
                ->get()
            : collect();

        return $this->success('Schedule retrieved successfully.', $events);
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
        $driver = $this->driver($user)->loadMissing('organization');
        $qrCode = QrCode::where('driver_id', $driver->id)->first();
        $organization = $driver->organization;

        return [
            'id' => (string) $driver->id,
            'driver_id' => (string) $driver->id,
            'name' => $driver->name,
            'email' => $user->email,
            'phone' => $driver->contact_number,
            'profile_picture' => $user->profile_picture,
            'avatar' => $user->profile_picture ? Storage::disk('public')->url($user->profile_picture) : null,
            'vehicle_number' => $driver->vehicle_number,
            'organization_id' => $driver->organization_id,
            'organization_name' => $organization?->name,
            'toda_name' => $organization?->name,
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
            ] : null,
            'qr_code_status' => $qrCode?->status ? strtolower($qrCode->status) : 'unregistered',
            'qr_identifier' => $qrCode?->qr_token,
            'account_status' => strtolower((string) ($driver->status ?: $user->status)),
            'shift_status' => $user->shift_status ?: 'Available',
            'must_change_password' => (bool) $user->must_change_password,
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
        if ($date) {
            $query->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
        }

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
