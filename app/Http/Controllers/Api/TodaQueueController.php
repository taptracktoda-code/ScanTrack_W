<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Models\User;
use App\Services\MobileNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TodaQueueController extends Controller
{
    public function remove(Request $request, OrganizationQueueEntry $entry, MobileNotificationService $notifications): JsonResponse
    {
        $manager = $request->user();
        abort_unless((int) $entry->organization_id === (int) $manager->organization_id, 404);
        if (is_string($request->input('details'))) {
            $request->merge(['details' => trim($request->input('details'))]);
        }
        $attributes = $request->validate([
            'reason' => ['required', 'in:left_terminal,other'],
            'details' => ['required_if:reason,other', 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($entry, $manager, $attributes, $notifications): void {
            // Same lock order as QR check-in and trip creation: organization, driver, entry.
            $organization = TodaOrganization::whereKey($manager->organization_id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($entry->driver_id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $driver->organization_id === (int) $organization->id, 404);
            $locked = OrganizationQueueEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'WAITING' || Trip::where('driver_id', $driver->id)->where('status', 'in_progress')->exists()) {
                throw ValidationException::withMessages(['queue' => ['Only a waiting driver can be removed. Refresh the queue and try again.']]);
            }
            $reason = $attributes['reason'] === 'left_terminal' ? 'Left terminal' : $attributes['details'];
            $locked->forceFill([
                'status' => 'CANCELLED',
                'completed_at' => now(),
                'removed_at' => now(),
                'removed_by' => $manager->id,
                'removal_reason' => $attributes['reason'],
                'removal_details' => $attributes['reason'] === 'other' ? $attributes['details'] : null,
            ])->save();
            // Close attendance when a manager records that the driver has left.
            $latest = DriverLog::where('driver_id', $driver->id)->whereDate('created_at', today())
                ->latest('created_at')->latest('id')->first();
            if ($latest?->attendance_type === 'ENTRY') {
                DriverLog::create([
                    'driver_id' => $driver->id, 'attendance_type' => 'EXIT',
                    'scanned_at' => now(), 'scan_method' => 'MANUAL',
                ]);
            }
            TerminalActivity::create([
                'admin_id' => $manager->id, 'driver_id' => $driver->id,
                'activity' => 'Driver removed from queue',
                'description' => 'Queue #'.$locked->queue_number.' at '.$organization->name.': '.$reason,
            ]);
            $notifications->notifyDriver($driver, 'Removed from queue',
                'Your manager removed you from the queue: '.$reason.'. Check in again to join the end of the queue.',
                'system', ['screen' => 'dashboard', 'entry_id' => $locked->id, 'reason' => $reason]);
        });

        return response()->json(['success' => true, 'message' => 'Driver removed from queue.']);
    }

    public function activity(Request $request): JsonResponse
    {
        $entries = OrganizationQueueEntry::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereNotNull('removed_at')->orderByDesc('removed_at')->orderByDesc('id')
            ->paginate(30);
        $drivers = Driver::whereIn('id', $entries->pluck('driver_id'))->get()->keyBy('id');
        $managers = User::whereIn('id', $entries->pluck('removed_by')->filter())->get()->keyBy('id');
        $entries->through(fn ($entry) => [
            'id' => $entry->id,
            'driver_name' => $drivers->get($entry->driver_id)?->name ?? 'Deleted driver',
            'manager_name' => $managers->get($entry->removed_by)?->name ?? 'Former manager',
            'reason' => $entry->removal_reason === 'left_terminal' ? 'Left terminal' : $entry->removal_details,
            'removed_at' => $entry->removed_at,
        ]);

        return response()->json($entries);
    }

    public function myToda(Request $request): JsonResponse
    {
        $account = $request->user();
        $identifier = (string) $account->user_id;
        abort_unless(str_starts_with($identifier, 'DRIVER-') && ctype_digit(substr($identifier, 7)), 404);
        $driver = Driver::findOrFail((int) substr($identifier, 7));
        $organization = TodaOrganization::find($driver->organization_id);
        // A small explicit projection avoids exposing managers' private account/contact fields.
        $managers = $organization ? User::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(role) = ?', [strtolower(User::ROLE_MANAGER)])
            ->whereRaw('LOWER(status) = ?', ['active'])->orderBy('name')->orderBy('id')
            ->get()->map(fn ($manager) => [
                'id' => $manager->id, 'name' => $manager->name,
                'avatar' => $manager->profile_picture ? Storage::disk('public')->url($manager->profile_picture) : null,
            ])->values() : [];

        return response()->json(['data' => [
            'id' => $organization?->id, 'name' => $organization?->name,
            'official_contact_number' => $organization?->contact_number,
            'managers' => $managers,
        ]]);
    }
}
