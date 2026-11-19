@php
    use App\Services\OperationsReport;
    $tripCountLabel = $isManagerQueue ? 'TODA trips completed (all time)' : 'Trips completed (all time)';
@endphp

<section class="queue-metrics" aria-label="Queue and terminal totals">
    @foreach ($summary as $label => $value)
        <article class="reports-metric">
            <div class="reports-metric-heading">{{ $label }}</div>
            <strong class="reports-metric-value">{{ number_format($value) }}</strong>
        </article>
    @endforeach
</section>

<section class="reports-table-panel" aria-labelledby="queue-records-heading">
    <div class="reports-table-heading">
        <div>
            <h2 id="queue-records-heading">Queue and attendance records</h2>
            <p>Current positions follow each TODA’s live queue. Queue numbers identify individual sessions.</p>
        </div>
        <span class="reports-record-count">{{ number_format($records->total()) }} records</span>
    </div>
    <div class="table-responsive queue-table-wrap" data-queue-scroll>
        <table class="table reports-table queue-table mb-0" role="table" aria-labelledby="queue-records-heading">
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col" role="columnheader">Driver</th>
                    <th scope="col" role="columnheader">TODA</th>
                    <th scope="col" role="columnheader">Terminals</th>
                    <th scope="col" role="columnheader">Entry / Exit</th>
                    <th scope="col" role="columnheader">Queue no. / Position</th>
                    <th scope="col" role="columnheader">Joined queue</th>
                    <th scope="col" role="columnheader">Status</th>
                    <th scope="col" role="columnheader">Waiting time</th>
                    <th scope="col" role="columnheader">Trip start / Completion</th>
                    <th scope="col" role="columnheader">{{ $tripCountLabel }}</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @forelse ($records as $row)
                    <tr role="row" class="queue-record">
                        <th scope="row" role="rowheader" class="reports-driver-name">
                            {{ $row['driver'] }}
                            <div class="small text-muted">ID {{ $row['driver_id'] }}</div>
                        </th>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">TODA</span>
                            {{ $row['organization'] }}
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">Terminals</span>
                            <div>Assigned · {{ $row['assigned_terminal'] ?? 'Unassigned' }}</div>
                            <div>Recorded · {{ $row['terminal'] ?? OperationsReport::UNKNOWN }}</div>
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">Entry / Exit</span>
                            <div>In · {{ OperationsReport::timestamp(OperationsReport::scanTime($row['entry'])) }}</div>
                            <div>Out · {{ OperationsReport::timestamp(OperationsReport::scanTime($row['exit'])) }}</div>
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">Queue no. / Position</span>
                            <div>{{ $row['queue'] ? '#'.$row['queue']->queue_number : OperationsReport::UNKNOWN }}</div>
                            <div>Position · {{ $row['position'] ?? OperationsReport::UNKNOWN }}</div>
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">Joined queue</span>
                            {{ OperationsReport::timestamp($row['queue']?->created_at) }}
                        </td>
                        <td role="cell" class="queue-record-status">
                            <span class="queue-cell-label" aria-hidden="true">Status</span>
                            {{ OperationsReport::STATUSES[$row['queue']?->status] ?? OperationsReport::UNKNOWN }}
                            @if ($row['queue']?->removed_at)
                                <div class="small text-muted">Manual removal</div>
                            @endif
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">Waiting time</span>
                            {{ OperationsReport::duration($row['waiting']) }}
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">Trip start / Completion</span>
                            @if ($row['trip'])
                                <div>Start · {{ OperationsReport::timestamp($row['trip']->departure_time) }}</div>
                                <div>End · {{ OperationsReport::timestamp($row['trip']->arrival_time) }}</div>
                            @else
                                No Trip Recorded
                            @endif
                        </td>
                        <td role="cell">
                            <span class="queue-cell-label" aria-hidden="true">{{ $tripCountLabel }}</span>
                            {{ $row['total_trips'] }}
                        </td>
                    </tr>
                @empty
                    <tr role="row"><td role="cell" colspan="10" class="reports-table-empty">No records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($records->hasPages())
        <div class="queue-pagination">{{ $records->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    @endif
</section>
