<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TodaQueueController extends Controller
{
    public function remove(Request $request, OrganizationQueueEntry $entry): JsonResponse
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

        app(\App\Services\QueueRemovalService::class)->remove($manager, $entry, $attributes['reason'], $attributes['details'] ?? null);

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
