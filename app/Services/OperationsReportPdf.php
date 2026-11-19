<?php

namespace App\Services;

use App\Models\Driver;
use Carbon\CarbonInterface;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;

class OperationsReportPdf
{
    public function render(string $type, array $filters, array $snapshot): string
    {
        $sections = match ($type) {
            'entry-exit' => [$this->attendance($snapshot['attendance'])],
            'queue-monitoring' => [$this->queue($snapshot['queue'])],
            'trip-history' => [$this->trips($snapshot['trips'])],
            'terminal-activity' => [$this->activity($snapshot, $filters)],
            'combined' => [
                $this->attendance($snapshot['attendance']),
                $this->queue($snapshot['queue']),
                $this->trips($snapshot['trips']),
                $this->roster($snapshot['roster']),
            ],
        };

        $title = $type === 'combined'
            ? 'Driver Entry, Exit and Queue Monitoring Report'
            : OperationsReport::TYPES[$type];

        return $this->renderDocument($title, $this->period($filters), $sections);
    }

    public function renderOrganization(string $organization, string $date, array $summary, Collection $drivers): string
    {
        $sections = [
            [
                'title' => 'Organization summary · '.$date,
                'headers' => ['Total drivers' => 20, 'Active drivers' => 20, 'Vehicles' => 20, 'Entries today' => 20, 'Exits today' => 20],
                'rows' => collect([[
                    $summary['totalDrivers'], $summary['activeDrivers'], $summary['totalVehicles'],
                    $summary['todayEntries'], $summary['todayExits'],
                ]]),
            ],
            [
                'title' => 'Driver attendance totals',
                'headers' => [
                    'Driver and ID' => 20, 'License number' => 14, 'Contact number' => 14, 'Plate number' => 12,
                    'Status' => 10, 'All-time attendance' => 14, 'Last scan' => 16,
                ],
                'rows' => $drivers->map(fn (Driver $driver) => [
                    [$driver->name, 'ID' => $driver->id],
                    $driver->license_number ?? OperationsReport::UNKNOWN,
                    $driver->contact_number ?? OperationsReport::UNKNOWN,
                    $driver->vehicle_number ?? OperationsReport::UNKNOWN,
                    $driver->status,
                    ['Entries' => $driver->total_entries, 'Exits' => $driver->total_exits],
                    $this->dateTime($driver->last_scan_at),
                ]),
                'empty' => 'No drivers are registered in this TODA.',
            ],
        ];

        return $this->renderDocument('Organization Operations Report', $this->period([]), $sections, $organization);
    }

    private function renderDocument(string $title, string $period, array $sections, ?string $organization = null): string
    {
        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
        ]);
        $pdf->loadHtml(view('reports.operations-pdf', [
            'title' => $title,
            'period' => $period,
            'organization' => $organization,
            'generatedAt' => $this->dateTime(now()),
            'sections' => $sections,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_script(function ($page, $pages, $canvas, $fonts): void {
            $font = $fonts->getFont('DejaVu Sans');
            $label = "Page {$page} of {$pages}";
            $right = $canvas->get_width() - 34;
            $bottom = $canvas->get_height();
            $ink = [0.30, 0.34, 0.39];
            $canvas->line(34, $bottom - 29, $right, $bottom - 29, [0.83, 0.86, 0.89], 0.5);
            $canvas->text(34, $bottom - 22, 'SCANTRACK  /  SCFTODA', $font, 8, $ink);
            $canvas->text($right - $fonts->getTextWidth($label, $font, 8), $bottom - 22, $label, $font, 8, $ink);
        });

        return $pdf->output();
    }

    private function period(array $filters): string
    {
        return match (true) {
            isset($filters['start_date'], $filters['end_date']) => $filters['start_date'].' through '.$filters['end_date'],
            isset($filters['start_date']) => 'From '.$filters['start_date'],
            isset($filters['end_date']) => 'Through '.$filters['end_date'],
            default => 'All recorded dates',
        };
    }

    private function attendance(Collection $visits): array
    {
        return [
            'title' => 'Terminal attendance',
            'headers' => ['Driver and ID' => 28, 'TODA' => 24, 'Entry date and time' => 24, 'Exit date and time' => 24],
            'rows' => $visits->map(fn ($row) => [
                ...$this->identity($row),
                $this->dateTime(OperationsReport::scanTime($row['entry'])),
                $this->dateTime(OperationsReport::scanTime($row['exit'])),
            ]),
        ];
    }

    private function queue(Collection $sessions): array
    {
        return [
            'title' => 'Queue sessions',
            'headers' => [
                'Driver / TODA' => 20, 'Attendance' => 19, 'Queue session' => 21,
                'Waiting time' => 11, 'Trip' => 19, 'Trips completed (all time)' => 10,
            ],
            'rows' => $sessions->map(fn ($row) => [
                [$row['driver'], 'ID' => $row['driver_id'], $row['organization']],
                [
                    'Entry' => $this->dateTime(OperationsReport::scanTime($row['entry'])),
                    'Exit' => $this->dateTime(OperationsReport::scanTime($row['exit'])),
                ],
                array_filter([
                    OperationsReport::STATUSES[$row['queue']->status] ?? OperationsReport::UNKNOWN,
                    $row['queue']->removed_at ? 'Manual removal' : null,
                    'No.' => $row['queue']->queue_number,
                    'Position' => $row['position'] ?? OperationsReport::UNKNOWN,
                    'Joined' => $this->dateTime($row['queue']->created_at),
                ], fn ($value) => $value !== null),
                OperationsReport::duration($row['waiting']),
                $row['trip'] ? [
                    'Start' => $this->dateTime($row['trip']->departure_time),
                    'End' => $this->dateTime($row['trip']->arrival_time),
                ] : 'No Trip Recorded',
                $row['total_trips'],
            ]),
        ];
    }

    private function trips(Collection $trips): array
    {
        return [
            'title' => 'Recorded trips',
            'headers' => [
                'Driver and ID' => 18, 'TODA' => 14, 'Trip ID and destination' => 20,
                'Departure' => 15, 'Completion' => 15, 'Trip status' => 8, 'Trips completed (all time)' => 10,
            ],
            'rows' => $trips->map(fn ($row) => [
                ...$this->identity($row),
                [$row['trip']->destination, $row['trip']->trip_id],
                $this->dateTime($row['trip']->departure_time),
                $this->dateTime($row['trip']->arrival_time),
                ucfirst(str_replace('_', ' ', $row['trip']->status)),
                $row['total_trips'],
            ]),
            'empty' => 'No Trip Recorded',
        ];
    }

    private function activity(array $snapshot, array $filters): array
    {
        $events = collect();
        $record = function (array $row, ?CarbonInterface $time, string $event) use ($events, $filters): void {
            if (! $time) {
                return;
            }
            $date = $time->copy()->timezone(OperationsReport::ZONE)->toDateString();
            if ($date >= ($filters['start_date'] ?? '') && $date <= ($filters['end_date'] ?? '9999-12-31')) {
                $events->push([$time, [...$this->identity($row), $this->dateTime($time), $event]]);
            }
        };

        foreach ($snapshot['attendance'] as $row) {
            $record($row, OperationsReport::scanTime($row['entry']), 'Terminal entry');
            $record($row, OperationsReport::scanTime($row['exit']), 'Terminal exit');
        }
        foreach ($snapshot['queue'] as $row) {
            $queue = $row['queue'];
            $suffix = ' · Queue #'.$queue->queue_number;
            $record($row, $queue->created_at, 'Joined queue'.$suffix);
            $record($row, $queue->served_at, 'Dispatched'.$suffix);
            $event = match ($queue->status) {
                'CANCELLED' => $queue->removed_at ? 'Manually removed from queue' : 'Removed from queue',
                'COMPLETED' => 'Queue session completed',
                default => null,
            };
            if ($event) {
                $record($row, $queue->removed_at ?? $queue->completed_at, $event.$suffix);
            }
        }
        foreach ($snapshot['trips'] as $row) {
            $record($row, $row['trip']->departure_time, 'Trip started · '.$row['trip']->destination);
            $record($row, $row['trip']->arrival_time, 'Trip completed · '.$row['trip']->destination);
        }

        return [
            'title' => 'Terminal operations timeline',
            'headers' => ['Driver and ID' => 24, 'TODA' => 20, 'Date and time' => 22, 'Activity' => 34],
            'rows' => $events->sortByDesc(fn ($event) => $event[0])->pluck(1),
        ];
    }

    private function roster(Collection $drivers): array
    {
        return [
            'title' => 'Driver roster',
            'headers' => ['Driver and ID' => 52, 'Current TODA' => 48],
            'rows' => $drivers->map(fn ($row) => $this->identity($row)),
            'empty' => 'No drivers match these filters.',
        ];
    }

    private function identity(array $row): array
    {
        return [
            [$row['driver'], 'ID' => $row['driver_id']],
            $row['organization'],
        ];
    }

    private function dateTime(?CarbonInterface $time): string
    {
        return $time?->copy()->timezone(OperationsReport::ZONE)->format('d M Y, H:i:s') ?? OperationsReport::UNKNOWN;
    }
}
