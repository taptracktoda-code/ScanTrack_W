@php
    use App\Services\OperationsReport;
@endphp

<section class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-4" aria-label="Queue and terminal totals">
    @foreach ($summary as $label => $value)
        <div class="col">
            <article class="reports-metric h-100">
                <div class="reports-metric-heading"><span>{{ $label }}</span></div>
                <strong class="reports-metric-value">{{ number_format($value) }}</strong>
            </article>
        </div>
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
    <div class="table-responsive" data-queue-scroll tabindex="0" aria-label="Queue and attendance records table">
        <table class="table reports-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Driver</th>
                    <th scope="col">TODA</th>
                    <th scope="col">Terminal</th>
                    <th scope="col">Entry / Exit</th>
                    <th scope="col">Queue no. / Current position</th>
                    <th scope="col">Joined queue</th>
                    <th scope="col">Queue status</th>
                    <th scope="col">Total waiting</th>
                    <th scope="col">Trip start / Completion</th>
                    <th scope="col">Driver trips completed (all time)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $row)
                    <tr>
                        <th scope="row" class="reports-driver-name">{{ $row['driver'] }}<div class="small text-muted">ID {{ $row['driver_id'] }}</div></th>
                        <td>{{ $row['organization'] }}</td>
                        <td>{{ $row['terminal'] ?? OperationsReport::UNKNOWN }}</td>
                        <td class="text-nowrap">
                            <div>In {{ OperationsReport::timestamp(OperationsReport::scanTime($row['entry'])) }}</div>
                            <div>Out {{ OperationsReport::timestamp(OperationsReport::scanTime($row['exit'])) }}</div>
                        </td>
                        <td>
                            <div>{{ $row['queue'] ? '#'.$row['queue']->queue_number : OperationsReport::UNKNOWN }}</div>
                            <div>{{ $row['position'] ?? OperationsReport::UNKNOWN }}</div>
                        </td>
                        <td class="text-nowrap">{{ OperationsReport::timestamp($row['queue']?->created_at) }}</td>
                        <td>
                            {{ OperationsReport::STATUSES[$row['queue']?->status] ?? OperationsReport::UNKNOWN }}
                            @if ($row['queue']?->removed_at)
                                <div class="small text-muted">Manual removal</div>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ OperationsReport::duration($row['waiting']) }}</td>
                        <td class="text-nowrap">
                            @if ($row['trip'])
                                <div>{{ OperationsReport::timestamp($row['trip']->departure_time) }}</div>
                                <div>{{ OperationsReport::timestamp($row['trip']->arrival_time) }}</div>
                            @else
                                No Trip Recorded
                            @endif
                        </td>
                        <td>{{ $row['total_trips'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="reports-table-empty">No records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($records->hasPages())
        <div class="px-3 pt-3">{{ $records->links('pagination::bootstrap-5') }}</div>
    @endif
</section>
