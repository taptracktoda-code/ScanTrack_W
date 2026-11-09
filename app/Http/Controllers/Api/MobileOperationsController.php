<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverIssueRequest;
use App\Models\DriverLog;
use App\Models\OrganizationQueueEntry;
use App\Models\TodaOrganization;
use App\Models\Trip;
use App\Services\MobileNotificationService;
use App\Services\PhilippineDay;
use App\Services\QueueRemovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileOperationsController extends Controller
{
    public function report(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        [$start, $end, $date] = PhilippineDay::bounds($request->input('date'));
        $organization = $request->user()->organization_id;
        $trips = Trip::where('organization_id', $organization)->where('status', 'completed')
            ->where('arrival_time', '>=', $start)->where('arrival_time', '<', $end);
        $logs = DriverLog::where('organization_id', $organization)->where('created_at', '>=', $start)->where('created_at', '<', $end);

        return response()->json(['data' => [
            'date' => $date, 'timezone' => 'Asia/Manila',
            'completed_trips' => (clone $trips)->count(), 'passenger_total' => (int) (clone $trips)->sum('passenger_count'),
            'unique_drivers_checked_in' => (clone $logs)->where('attendance_type', 'ENTRY')->distinct()->count('driver_id'),
            'entry_events' => (clone $logs)->where('attendance_type', 'ENTRY')->count(),
            'exit_events' => (clone $logs)->where('attendance_type', 'EXIT')->count(),
            'queue_removals' => OrganizationQueueEntry::where('organization_id', $organization)
                ->where('removed_at', '>=', $start)->where('removed_at', '<', $end)->count(),
        ]]);
    }

    private function driver(Request $request): Driver
    {
        $identifier = (string) $request->user()->user_id;
        abort_unless(str_starts_with($identifier, 'DRIVER-') && ctype_digit(substr($identifier, 7)), 404);
        return Driver::findOrFail((int) substr($identifier, 7));
    }

    public function issues(Request $request): JsonResponse
    {
        $query = DriverIssueRequest::with(['driver', 'queueEntry', 'reviewer']);
        if ($request->user()->isManager()) {
            $query->where('organization_id', $request->user()->organization_id);
        } else {
            $query->where('driver_id', $this->driver($request)->id);
        }
        $pending = (clone $query)->where('status', 'pending')->count();
        $page = $query->latest('id')->paginate(20);
        $page->through(fn ($issue) => $this->issueData($issue));
        return response()->json(['data' => $page->items(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'pending_count' => $pending]);
    }

    public function submit(Request $request, MobileNotificationService $notifications): JsonResponse
    {
        if (is_string($request->input('explanation'))) $request->merge(['explanation' => trim($request->input('explanation'))]);
        $attributes = $request->validate([
            'type' => ['required', 'in:vehicle_breakdown,leave_queue,other'], 'explanation' => ['required', 'string', 'max:500'],
            'queue_entry_id' => ['required_unless:type,other', 'nullable', 'integer'], 'client_request_id' => ['required', 'string', 'max:80'],
        ]);
        $driver = $this->driver($request);
        $issue = DB::transaction(function () use ($driver, $attributes, $notifications): DriverIssueRequest {
            TodaOrganization::whereKey($driver->organization_id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $replay = DriverIssueRequest::where('client_request_id', $attributes['client_request_id'])->first();
            if ($replay) {
                abort_unless($replay->driver_id === $driver->id, 404);
                return $replay;
            }
            $entry = null;
            if (isset($attributes['queue_entry_id'])) {
                $entry = OrganizationQueueEntry::whereKey($attributes['queue_entry_id'])
                    ->where('driver_id', $driver->id)->where('organization_id', $driver->organization_id)->lockForUpdate()->firstOrFail();
                if ($entry->status !== 'WAITING') throw ValidationException::withMessages(['queue' => ['Join the waiting queue before submitting this request.']]);
                if (DriverIssueRequest::where('pending_entry_id', $entry->id)->exists()) {
                    throw ValidationException::withMessages(['queue' => ['You already have a pending request for this queue entry.']]);
                }
            }
            $issue = DriverIssueRequest::create([
                ...$attributes, 'driver_id' => $driver->id, 'organization_id' => $driver->organization_id,
                'queue_entry_id' => $entry?->id, 'pending_entry_id' => $entry?->id, 'status' => 'pending',
            ]);
            $notifications->notifyOrganizationManagers((int) $driver->organization_id, 'Driver issue request',
                $driver->name.' submitted a request for review.', 'system', ['screen' => 'issues', 'issue_id' => $issue->id]);
            return $issue;
        });
        return response()->json(['success' => true, 'data' => $this->issueData($issue->load(['driver', 'queueEntry', 'reviewer']))], 201);
    }

    public function decide(Request $request, DriverIssueRequest $issue, QueueRemovalService $removal, MobileNotificationService $notifications): JsonResponse
    {
        abort_unless((int) $issue->organization_id === (int) $request->user()->organization_id, 404);
        if (is_string($request->input('decision_reason'))) $request->merge(['decision_reason' => trim($request->input('decision_reason'))]);
        $attributes = $request->validate([
            'decision' => ['required', 'in:approved,rejected'], 'decision_reason' => ['required', 'string', 'max:500'],
            'confirm_removal' => $issue->queue_entry_id !== null
                ? ['required_if:decision,approved', 'boolean', 'accepted_if:decision,approved']
                : ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $issue, $attributes, $removal, $notifications): void {
            TodaOrganization::whereKey($issue->organization_id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($issue->driver_id)->lockForUpdate()->firstOrFail();
            $entry = $issue->queue_entry_id !== null
                ? OrganizationQueueEntry::whereKey($issue->queue_entry_id)->lockForUpdate()->firstOrFail() : null;
            $locked = DriverIssueRequest::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') throw ValidationException::withMessages(['request' => ['This request has already been reviewed. Refresh to see the decision.']]);
            if ($attributes['decision'] === 'approved') {
                if ((int) $driver->organization_id !== (int) $issue->organization_id || ($entry !== null && $entry->status !== 'WAITING')) {
                    throw ValidationException::withMessages(['queue' => ['This request is no longer eligible for approval in your TODA. Refresh the request; it can be rejected with an explanation.']]);
                }
                if ($entry !== null) $removal->remove($request->user(), $entry, 'other', $attributes['decision_reason']);
            }
            $locked->update(['status' => $attributes['decision'], 'decision_reason' => $attributes['decision_reason'],
                'reviewed_by' => $request->user()->id, 'decided_at' => now(), 'pending_entry_id' => null]);
            $notifications->notifyDriver($driver, 'Issue request '.$attributes['decision'], $attributes['decision_reason'],
                'system', ['screen' => 'issues', 'issue_id' => $issue->id]);
        });
        return response()->json(['success' => true]);
    }

    private function issueData(DriverIssueRequest $issue): array
    {
        return [
            'id' => $issue->id, 'queue_entry_id' => $issue->queue_entry_id, 'driver_name' => $issue->driver?->name,
            'type' => $issue->type, 'explanation' => $issue->explanation, 'status' => $issue->status,
            'created_at' => $issue->created_at?->toIso8601String(), 'decided_at' => $issue->decided_at?->toIso8601String(),
            'decision_reason' => $issue->decision_reason, 'manager_name' => $issue->reviewer?->name,
            'queue_status' => $issue->queueEntry?->status,
            'can_approve' => $issue->status === 'pending' && ($issue->queue_entry_id === null || $issue->queueEntry?->status === 'WAITING')
                && (int) $issue->driver?->organization_id === (int) $issue->organization_id,
        ];
    }
}
