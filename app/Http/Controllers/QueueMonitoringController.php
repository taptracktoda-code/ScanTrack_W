<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverLog;
use App\Models\TodaOrganization;
use App\Services\OperationsReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class QueueMonitoringController extends Controller
{
    public function index(Request $request, OperationsReport $reports): View
    {
        $managerOrganization = $request->user()->isManager() ? $request->user()->organization : null;
        $organizationId = $this->managedOrganizationId($request);
        $routePrefix = $organizationId === null ? '' : 'manager.';
        $filters = $reports->filters($request, $organizationId);

        return view('queues.index', $this->monitor($request, $reports, $filters, $organizationId) + [
            'filters' => $filters,
            'managerOrganization' => $managerOrganization,
            'indexRoute' => $routePrefix.'queues.monitoring',
            'feedRoute' => $routePrefix.'queues.feed',
            'reportsRoute' => $routePrefix.'reports',
            'terminals' => DriverLog::query()->whereNotNull('terminal_id')->where('terminal_id', '<>', '')
                ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
                ->distinct()->orderBy('terminal_id')->pluck('terminal_id'),
            'organizations' => $organizationId === null ? TodaOrganization::orderBy('name')->get(['id', 'name']) : collect(),
            'drivers' => Driver::orderBy('name')
                ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
                ->get(['id', 'name']),
            'reportTypes' => OperationsReport::TYPES,
            'statuses' => OperationsReport::STATUSES,
        ]);
    }

    public function feed(Request $request, OperationsReport $reports): Response
    {
        $organizationId = $this->managedOrganizationId($request);
        $filters = $reports->filters($request, $organizationId);
        $response = response()->view('queues.records', $this->monitor($request, $reports, $filters, $organizationId))
            ->header('Cache-Control', 'private, no-cache');
        $response->setEtag(hash('sha256', $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    private function managedOrganizationId(Request $request): ?int
    {
        return $request->user()->isManager() ? (int) $request->user()->organization_id : null;
    }

    private function monitor(Request $request, OperationsReport $reports, array $filters, ?int $organizationId): array
    {
        $snapshot = $reports->snapshot($filters, managedOrganizationId: $organizationId);
        $pageSize = 25;
        $total = $snapshot['records']->count();
        $page = min(max(1, (int) $request->query('page', 1)), max(1, (int) ceil($total / $pageSize)));
        $records = new LengthAwarePaginator($snapshot['records']->forPage($page, $pageSize), $total, $pageSize, $page, [
            'path' => route($organizationId === null ? 'queues.monitoring' : 'manager.queues.monitoring'),
            'query' => $filters,
        ]);

        return ['summary' => $snapshot['summary'], 'records' => $records, 'isManagerQueue' => $organizationId !== null];
    }
}
