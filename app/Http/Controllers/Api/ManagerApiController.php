<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QrScanRequest;
use App\Models\Driver;
use App\Models\OrganizationDestination;
use App\Models\OrganizationEvent;
use App\Models\OrganizationQueueEntry;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ManagerDriverAccountService;
use App\Services\MobileNotificationService;
use App\Services\QrScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagerApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $manager = User::query()->where('email', $credentials['email'])->first();

        if (! $manager || ! Hash::check($credentials['password'], $manager->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }
        if (! $manager->isManager() || strcasecmp((string) $manager->status, 'Active') !== 0) {
            return response()->json(['success' => false, 'message' => 'This account is not authorized for the manager application.'], 403);
        }
        if (! $manager->organization()->where('status', 'Active')->exists()) {
            return response()->json(['success' => false, 'message' => 'Your assigned organization is inactive.'], 403);
        }

        $organization = $manager->organization;

        return response()->json([
            'success' => true,
            'token' => $manager->createToken('scantrack-manager')->plainTextToken,
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'organization_id' => $manager->organization_id,
                'organization_name' => $organization?->name,
                'organization' => $organization ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ] : null,
                'must_change_password' => (bool) $manager->must_change_password,
            ],
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
        $manager = $request->user();

        if (! Hash::check($attributes['current_password'], $manager->password)) {
            return response()->json(['success' => false, 'message' => 'The current password is incorrect.'], 422);
        }

        $manager->forceFill(['password' => Hash::make($attributes['password']), 'must_change_password' => false])->save();

        return response()->json(['success' => true, 'message' => 'Password changed successfully.', 'must_change_password' => false]);
    }

    public function uploadProfileAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $manager = $request->user();
        $path = $request->file('avatar')->store('profile-pictures', 'public');
        if (! $path) {
            return response()->json(['success' => false, 'message' => 'The profile picture could not be saved. Please try again.'], 500);
        }

        $previousPicture = $manager->profile_picture;
        $manager->forceFill(['profile_picture' => $path])->save();

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
        $manager = $request->user();
        $previousPicture = $manager->profile_picture;
        $manager->forceFill(['profile_picture' => null])->save();

        if ($previousPicture) {
            Storage::disk('public')->delete($previousPicture);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile picture removed successfully.',
            'data' => ['avatar' => null, 'avatar_url' => null],
        ]);
    }

    public function drivers(Request $request): JsonResponse
    {
        $manager = $request->user();
        $organization = $this->activeOrganization($manager);

        $drivers = Driver::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();
        $driverUsers = User::query()
            ->whereRaw('LOWER(role) = ?', [strtolower(User::ROLE_DRIVER)])
            ->whereIn('user_id', $drivers->map(fn (Driver $driver): string => 'DRIVER-'.strtoupper((string) $driver->id)))
            ->get()
            ->keyBy('user_id');

        $drivers = $drivers
            ->map(function (Driver $driver) use ($driverUsers): array {
                $shiftStatus = $driverUsers->get('DRIVER-'.strtoupper((string) $driver->id))?->shift_status ?: 'Available';

                return [
                    'id' => $driver->id,
                    'driver_id' => 'DRIVER-'.$driver->id,
                    'name' => $driver->name,
                    'vehicle_number' => $driver->vehicle_number,
                    'status' => $driver->status,
                    'shift_status' => $shiftStatus,
                    'duty_status' => strcasecmp($shiftStatus, 'Off Duty') === 0 ? 'off_duty' : 'on_duty',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Drivers retrieved successfully.',
            'drivers' => $drivers,
        ]);
    }

    public function tripDestinations(Request $request): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $destinations = OrganizationDestination::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Trip destinations retrieved successfully.',
            'destinations' => $destinations,
        ]);
    }

    public function createTripDestination(Request $request): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $name = $request->input('name');
        if (is_string($name)) {
            $request->merge(['name' => trim($name)]);
        }
        $attributes = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('organization_destinations', 'name')
                    ->where('organization_id', $organization->id),
            ],
        ]);

        $destination = OrganizationDestination::create([
            'organization_id' => $organization->id,
            'created_by' => $request->user()->id,
            'name' => trim($attributes['name']),
            'status' => 'Active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip destination created successfully.',
            'destination' => $destination,
        ], 201);
    }

    public function deactivateTripDestination(Request $request, OrganizationDestination $destination): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        abort_unless($destination->organization_id === $organization->id, 404);
        $destination->update(['status' => 'Inactive']);

        return response()->json([
            'success' => true,
            'message' => 'Trip destination deactivated successfully.',
        ]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $events = OrganizationEvent::query()
            ->where('organization_id', $organization->id)
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Schedules retrieved successfully.',
            'schedules' => $events,
        ]);
    }

    public function createSchedule(Request $request): JsonResponse
    {
        $manager = $request->user();
        $organization = $this->activeOrganization($manager);
        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $event = OrganizationEvent::create([
            ...$attributes,
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule created successfully.',
            'schedule' => $event,
        ], 201);
    }

    public function updateSchedule(Request $request, OrganizationEvent $event): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        abort_unless($event->organization_id === $organization->id, 404);

        $attributes = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'event_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
        validator([
            'start_time' => $attributes['start_time'] ?? $event->start_time,
            'end_time' => $attributes['end_time'] ?? $event->end_time,
        ], ['end_time' => ['after:start_time']])->validate();

        $event->update($attributes);

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully.',
            'schedule' => $event->refresh(),
        ]);
    }

    public function deleteSchedule(Request $request, OrganizationEvent $event): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        abort_unless($event->organization_id === $organization->id, 404);
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());

        $recentActivity = TerminalActivity::with('driver')
            ->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereIn('activity', ['Driver entered terminal', 'Driver exited terminal'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (TerminalActivity $activity): array => [
                'id' => $activity->id,
                'driver_id' => $activity->driver_id,
                'driver_name' => $activity->driver?->name,
                'plate_number' => $activity->driver?->vehicle_number,
                'transaction' => $activity->activity === 'Driver entered terminal' ? 'entry' : 'exit',
                'timestamp' => $activity->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'organization' => ['id' => $organization->id, 'name' => $organization->name],
                'summary' => [
                    'total_drivers' => Driver::query()
                        ->where('organization_id', $organization->id)
                        ->count(),
                    'active_drivers' => Driver::query()
                        ->where('organization_id', $organization->id)
                        ->whereRaw('LOWER(status) = ?', ['active'])
                        ->count(),
                    'waiting_drivers' => OrganizationQueueEntry::query()
                        ->where('organization_id', $organization->id)
                        ->where('status', 'WAITING')
                        ->count(),
                    'on_trip' => OrganizationQueueEntry::query()
                        ->where('organization_id', $organization->id)
                        ->where('status', 'SERVING')
                        ->count(),
                    'recent_activity' => $recentActivity,
                ],
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function scanQr(QrScanRequest $request, QrScanService $scanService): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $data = $request->validated();
        $data['admin_id'] = $request->user()->id;
        $data['organization_id'] = $organization->id;
        $result = $scanService->handle($data);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function registerDriver(
        Request $request,
        ManagerDriverAccountService $driverAccounts,
        MobileNotificationService $notifications,
    ): JsonResponse
    {
        $manager = $request->user();
        if ($manager->must_change_password) {
            return response()->json(['success' => false, 'message' => 'Change your temporary password before registering drivers.'], 403);
        }

        $organization = TodaOrganization::query()
            ->whereKey($manager->organization_id)
            ->where('status', 'Active')
            ->first();
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Your assigned organization is not active.'], 403);
        }

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
        $notifications->notifyUser(
            $manager,
            'Driver account created',
            "The account for {$result['driver']->name} is ready.",
            'account',
            ['screen' => 'driver-registration', 'driver_id' => $result['driver']->id],
        );
        $notifications->notifyDriver(
            $result['driver'],
            'Your ScanTrack account is ready',
            'Sign in with the credentials sent to your email.',
            'account',
            ['screen' => 'login'],
        );

        return response()->json([
            'success' => true,
            'message' => $emailSent
                ? 'Driver account created and activated. Login credentials were sent to the driver email.'
                : 'Driver account created and activated, but the credential email failed. Check mail configuration and send a secure password reset.',
            'driver' => [
                'id' => $result['driver']->id,
                'name' => $result['driver']->name,
                'status' => $result['driver']->status,
            ],
        ], 201);
    }

    public function queue(Request $request): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $current = OrganizationQueueEntry::with('driver')
            ->where('organization_id', $organization->id)
            ->where('status', 'SERVING')
            ->first();
        $waiting = OrganizationQueueEntry::with('driver')
            ->where('organization_id', $organization->id)
            ->where('status', 'WAITING')
            ->orderBy('queue_number')
            ->get();
        $lastServed = OrganizationQueueEntry::with('driver')
            ->where('organization_id', $organization->id)
            ->where('status', 'COMPLETED')
            ->latest('completed_at')
            ->first();

        return response()->json([
            'success' => true,
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'current' => $current,
            'waiting_count' => $waiting->count(),
            'waiting' => $waiting,
            'last_served' => $lastServed,
        ]);
    }

    public function enqueueDriver(Request $request, MobileNotificationService $notifications): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $attributes = $request->validate(['driver_id' => ['required', 'integer', 'exists:drivers,id']]);
        $driver = Driver::query()->whereKey($attributes['driver_id'])
            ->where('organization_id', $organization->id)
            ->where('status', 'Active')
            ->first();
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Choose an active driver from your organization.'], 422);
        }

        $entry = DB::transaction(function () use ($organization, $driver, $request): OrganizationQueueEntry {
            TodaOrganization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $alreadyQueued = OrganizationQueueEntry::query()
                ->where('organization_id', $organization->id)
                ->where('driver_id', $driver->id)
                ->whereIn('status', ['WAITING', 'SERVING'])
                ->exists();
            if ($alreadyQueued) {
                throw ValidationException::withMessages(['driver_id' => ['This driver is already in the active queue.']]);
            }

            $queueNumber = (int) OrganizationQueueEntry::where('organization_id', $organization->id)->max('queue_number') + 1;
            $entry = OrganizationQueueEntry::create([
                'organization_id' => $organization->id,
                'driver_id' => $driver->id,
                'queue_number' => $queueNumber,
                'status' => 'WAITING',
                'queued_by' => $request->user()->id,
            ]);
            TerminalActivity::create([
                'admin_id' => $request->user()->id,
                'driver_id' => $driver->id,
                'activity' => 'Queue driver added',
                'description' => 'Queue #'.$queueNumber.' at '.$organization->name,
            ]);

            return $entry;
        });

        $notifications->notifyDriver(
            $driver,
            'You were added to the queue',
            "Your queue number is {$entry->queue_number} at {$organization->name}.",
            'system',
            ['screen' => 'dashboard', 'queue_number' => $entry->queue_number],
        );

        return response()->json(['success' => true, 'message' => 'Driver added to the queue.', 'entry' => $entry], 201);
    }

    public function serveNext(Request $request, MobileNotificationService $notifications): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        $entry = DB::transaction(function () use ($organization, $request): ?OrganizationQueueEntry {
            TodaOrganization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            if (OrganizationQueueEntry::where('organization_id', $organization->id)->where('status', 'SERVING')->exists()) {
                return null;
            }

            $next = OrganizationQueueEntry::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'WAITING')
                ->orderBy('queue_number')
                ->lockForUpdate()
                ->first();
            if (! $next) {
                return null;
            }

            $next->update(['status' => 'SERVING', 'served_by' => $request->user()->id, 'served_at' => now()]);
            TerminalActivity::create([
                'admin_id' => $request->user()->id,
                'driver_id' => $next->driver_id,
                'activity' => 'Queue driver called',
                'description' => 'Queue #'.$next->queue_number.' at '.$organization->name,
            ]);

            return $next->load('driver');
        });

        if (! $entry) {
            return response()->json(['success' => false, 'message' => 'A driver is already being served or the queue is empty.'], 409);
        }

        if ($entry->driver) {
            $notifications->notifyDriver(
                $entry->driver,
                'It is your turn',
                "Queue number {$entry->queue_number} is being called at {$organization->name}.",
                'system',
                ['screen' => 'dashboard', 'queue_number' => $entry->queue_number],
            );
        }

        return response()->json(['success' => true, 'entry' => $entry]);
    }

    public function completeQueueEntry(
        Request $request,
        OrganizationQueueEntry $entry,
        MobileNotificationService $notifications,
    ): JsonResponse
    {
        $organization = $this->activeOrganization($request->user());
        abort_unless($entry->organization_id === $organization->id, 404);
        if ($entry->status !== 'SERVING') {
            return response()->json(['success' => false, 'message' => 'Only the current serving driver can be completed.'], 409);
        }

        DB::transaction(function () use ($entry, $organization, $request): void {
            $entry->update(['status' => 'COMPLETED', 'completed_at' => now()]);
            TerminalActivity::create([
                'admin_id' => $request->user()->id,
                'driver_id' => $entry->driver_id,
                'activity' => 'Queue driver served',
                'description' => 'Queue #'.$entry->queue_number.' at '.$organization->name,
            ]);
        });

        if ($entry->driver) {
            $notifications->notifyDriver(
                $entry->driver,
                'Queue service completed',
                "Your queue service at {$organization->name} is complete.",
                'system',
                ['screen' => 'dashboard', 'queue_number' => $entry->queue_number],
            );
        }

        return response()->json(['success' => true, 'message' => 'Driver marked as served.']);
    }

    private function activeOrganization(User $manager): TodaOrganization
    {
        return TodaOrganization::query()
            ->whereKey($manager->organization_id)
            ->where('status', 'Active')
            ->firstOrFail();
    }
}
