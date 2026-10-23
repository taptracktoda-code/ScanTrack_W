<?php

namespace App\Http\Controllers;

use App\Models\OrganizationEvent;
use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
use App\Models\Vehicle;
use App\Models\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerDashboardController extends Controller
{
    public function index(): View
    {
        $manager = auth()->user();
        $organization = TodaOrganization::query()
            ->whereKey($manager->organization_id)
            ->where('status', 'Active')
            ->firstOrFail();
        $events = OrganizationEvent::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->paginate(10);
        $stats = [
            ['label' => 'Drivers', 'value' => Driver::where('organization_id', $organization->id)->count()],
            ['label' => 'Active Drivers', 'value' => Driver::where('organization_id', $organization->id)->where('status', 'Active')->count()],
            ['label' => 'Vehicles', 'value' => Vehicle::where('organization_id', $organization->id)->count()],
            ['label' => 'Active QR Codes', 'value' => QrCode::where('status', 'Active')->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id))->count()],
            ['label' => 'Entries Today', 'value' => DriverLog::where('attendance_type', 'ENTRY')->whereDate('scanned_at', today())->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id))->count()],
            ['label' => 'Exits Today', 'value' => DriverLog::where('attendance_type', 'EXIT')->whereDate('scanned_at', today())->whereHas('driver', fn ($query) => $query->where('organization_id', $organization->id))->count()],
        ];

        return view('managers.dashboard', compact('organization', 'events', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $request->user();
        $organization = TodaOrganization::query()
            ->whereKey($manager->organization_id)
            ->where('status', 'Active')
            ->firstOrFail();
        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        OrganizationEvent::create([
            ...$attributes,
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
        ]);

        return redirect()->route('manager.dashboard')->with('status', 'Event added to your TODA schedule.');
    }

    public function update(Request $request, OrganizationEvent $event): RedirectResponse
    {
        $this->ensureOwnEvent($event);
        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $event->update($attributes);

        return redirect()->route('manager.dashboard')->with('status', 'Scheduled event updated.');
    }

    public function destroy(OrganizationEvent $event): RedirectResponse
    {
        $this->ensureOwnEvent($event);
        $event->delete();

        return redirect()->route('manager.dashboard')->with('status', 'Scheduled event deleted.');
    }

    private function ensureOwnEvent(OrganizationEvent $event): void
    {
        abort_unless(
            $event->organization_id === auth()->user()->organization_id
            && TodaOrganization::query()->whereKey($event->organization_id)->where('status', 'Active')->exists(),
            404
        );
    }
}
