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
        $filters = $reports->filters($request);

        return view('queues.index', $this->monitor($request, $reports, $filters) + [
            'filters' => $filters,
            'terminals' => DriverLog::query()->whereNotNull('terminal_id')->where('terminal_id', '<>', '')
                ->distinct()->orderBy('terminal_id')->pluck('terminal_id'),
            'organizations' => TodaOrganization::orderBy('name')->get(['id', 'name']),
            'drivers' => Driver::orderBy('name')->get(['id', 'name']),
            'reportTypes' => OperationsReport::TYPES,
            'statuses' => OperationsReport::STATUSES,
        ]);
    }

    public function feed(Request $request, OperationsReport $reports): Response
    {
        $filters = $reports->filters($request);
        $response = response()->view('queues.records', $this->monitor($request, $reports, $filters))
            ->header('Cache-Control', 'private, no-cache');
        $response->setEtag(hash('sha256', $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    private function monitor(Request $request, OperationsReport $reports, array $filters): array
    {
        $snapshot = $reports->snapshot($filters);
        $pageSize = 25;
        $total = $snapshot['records']->count();
        $page = min(max(1, (int) $request->query('page', 1)), max(1, (int) ceil($total / $pageSize)));
        $records = new LengthAwarePaginator($snapshot['records']->forPage($page, $pageSize), $total, $pageSize, $page, [
            'path' => route('queues.monitoring'),
            'query' => $filters,
        ]);

        return ['summary' => $snapshot['summary'], 'records' => $records];
    }
}
