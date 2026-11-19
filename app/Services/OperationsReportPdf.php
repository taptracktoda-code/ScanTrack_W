<?php

namespace App\Services;

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
                $this->assignments($snapshot['assignments']),
            ],
        };

        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
        ]);
        $pdf->loadHtml(view('reports.operations-pdf', [
            'title' => OperationsReport::TYPES[$type],
            'period' => $this->period($filters),
            'generatedAt' => OperationsReport::timestamp(now()),
            'sections' => $sections,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 119, $canvas->get_height() - 24, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.24, 0.31, 0.40]);

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
            'headers' => ['Driver and ID' => 20, 'TODA' => 15, 'Terminals' => 23, 'Entry date and time' => 21, 'Exit date and time' => 21],
            'rows' => $visits->map(fn ($row) => [
                ...$this->identity($row),
                OperationsReport::timestamp(OperationsReport::scanTime($row['entry'])),
                OperationsReport::timestamp(OperationsReport::scanTime($row['exit'])),
            ]),
        ];
    }

    private function queue(Collection $sessions): array
    {
        return [
            'title' => 'Queue sessions',
            'headers' => [
                'Driver and ID' => 12, 'TODA' => 8, 'Terminals' => 13, 'Entry / Exit date and time' => 14,
                'Queue no. / Current position' => 9, 'Joined' => 10, 'Queue status' => 8,
                'Total waiting' => 7, 'Trip start / completion' => 12, 'Driver trips completed (all time)' => 7,
            ],
            'rows' => $sessions->map(fn ($row) => [
                ...$this->identity($row),
                'In: '.OperationsReport::timestamp(OperationsReport::scanTime($row['entry']))
                    ."\nOut: ".OperationsReport::timestamp(OperationsReport::scanTime($row['exit'])),
                '#'.$row['queue']->queue_number."\n".($row['position'] ?? OperationsReport::UNKNOWN),
                OperationsReport::timestamp($row['queue']->created_at),
                (OperationsReport::STATUSES[$row['queue']->status] ?? OperationsReport::UNKNOWN)
                    .($row['queue']->removed_at ? "\nManual removal" : ''),
                OperationsReport::duration($row['waiting']),
                $row['trip'] ? OperationsReport::timestamp($row['trip']->departure_time)
                    ."\n".OperationsReport::timestamp($row['trip']->arrival_time) : 'No Trip Recorded',
                $row['total_trips'],
            ]),
        ];
    }

    private function trips(Collection $trips): array
    {
        return [
            'title' => 'Recorded trips',
            'headers' => [
                'Driver and ID' => 14, 'TODA' => 10, 'Terminals' => 16, 'Trip ID and destination' => 18,
                'Departure' => 14, 'Completion' => 14, 'Trip status' => 7, 'Driver trips completed (all time)' => 7,
            ],
            'rows' => $trips->map(fn ($row) => [
                ...$this->identity($row),
                $row['trip']->trip_id."\n".$row['trip']->destination,
                OperationsReport::timestamp($row['trip']->departure_time),
                OperationsReport::timestamp($row['trip']->arrival_time),
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
                $events->push([$time, [...$this->identity($row), OperationsReport::timestamp($time), $event]]);
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
            'headers' => ['Driver and ID' => 20, 'TODA' => 15, 'Terminals' => 20, 'Date and time' => 20, 'Activity' => 25],
            'rows' => $events->sortByDesc(fn ($event) => $event[0])->pluck(1),
        ];
    }

    private function assignments(Collection $drivers): array
    {
        return [
            'title' => 'Current driver terminal assignments',
            'headers' => ['Driver and ID' => 34, 'Current TODA' => 33, 'Assigned terminal' => 33],
            'rows' => $drivers->map(fn ($row) => [
                $row['driver']."\nID ".$row['driver_id'],
                $row['organization'],
                $row['assigned_terminal'] ?? 'Unassigned',
            ]),
            'empty' => 'No drivers match these filters.',
        ];
    }

    private function identity(array $row): array
    {
        return [
            $row['driver']."\nID ".$row['driver_id'],
            $row['organization'],
            'Assigned: '.($row['assigned_terminal'] ?? 'Unassigned')
                ."\nRecorded: ".($row['terminal'] ?? OperationsReport::UNKNOWN),
        ];
    }
}
