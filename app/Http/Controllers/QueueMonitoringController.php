<?php

namespace App\Http\Controllers;

use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use Illuminate\View\View;

class QueueMonitoringController extends Controller
{
    public function index(): View
    {
        $organizations = TodaOrganization::query()->orderBy('name')->get()->map(function (TodaOrganization $organization): array {
            $current = $organization->queueEntries()->with('driver')->where('status', 'SERVING')->first();
            $waiting = $organization->queueEntries()->where('status', 'WAITING')->count();
            $lastServed = $organization->queueEntries()->with('driver')->where('status', 'COMPLETED')->latest('completed_at')->first();

            return [
                'organization' => $organization,
                'current' => $current,
                'waiting' => $waiting,
                'last_served' => $lastServed,
                'state' => $current ? 'Serving' : ($waiting > 0 ? 'Waiting' : 'Idle'),
            ];
        });
        $activities = TerminalActivity::query()
            ->with(['admin', 'driver.organization'])
            ->whereIn('activity', ['Queue driver added', 'Queue driver called', 'Queue driver served'])
            ->latest()
            ->limit(30)
            ->get();

        return view('queues.index', compact('organizations', 'activities'));
    }
}