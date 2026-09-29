@extends('layouts.app')

@push('styles')
    <link href="{{ asset('css/analytics.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php
        $maxTrend = max(1, $trend->max(fn ($day) => max($day['entries'], $day['exits'])) ?? 0);
        $maxHour = max(1, $peakHours->max('scans') ?? 0);
        $maxOrganization = max(1, $organizations->max('total_scans') ?? 0);
        $queryFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $metricCards = [
            ['label' => 'Total Drivers', 'value' => $cards['drivers'], 'icon' => 'bi-people', 'tone' => 'blue'],
            ['label' => 'Active Drivers', 'value' => $cards['active_drivers'], 'icon' => 'bi-person-check', 'tone' => 'green'],
            ['label' => 'Organizations', 'value' => $cards['organizations'], 'icon' => 'bi-diagram-3', 'tone' => 'amber'],
            ['label' => 'Vehicles', 'value' => $cards['vehicles'], 'icon' => 'bi-truck', 'tone' => 'slate'],
            ['label' => 'QR Scans in Range', 'value' => $cards['scans'], 'icon' => 'bi-qr-code-scan', 'tone' => 'teal'],
            ['label' => 'Today\'s Entries', 'value' => $cards['today_entries'], 'icon' => 'bi-box-arrow-in-right', 'tone' => 'green'],
            ['label' => 'Today\'s Exits', 'value' => $cards['today_exits'], 'icon' => 'bi-box-arrow-right', 'tone' => 'amber'],
            ['label' => 'Currently Inside', 'value' => $cards['inside'], 'icon' => 'bi-person-standing', 'tone' => 'blue'],
        ];
    @endphp

    <header class="analytics-header">
        <div>
            <p class="analytics-eyebrow">SCANTRACK / OPERATIONS</p>
            <h1 class="analytics-title">Analytics</h1>
            <p class="analytics-subtitle">Driver activity and terminal scan patterns from recorded attendance.</p>
        </div>
        <div class="analytics-header-actions">
            <a class="btn analytics-button analytics-button-muted" href="{{ route('analytics', $queryFilters) }}" id="analytics-refresh">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh Data
            </a>
            <a class="btn analytics-button analytics-button-primary" href="{{ route('analytics.export', $queryFilters) }}">
                <i class="bi bi-download" aria-hidden="true"></i> Export CSV
            </a>
        </div>
    </header>

    <section class="analytics-filter-panel" aria-label="Analytics filters">
        <form action="{{ route('analytics') }}" method="GET" id="analytics-filter-form" class="analytics-filter-form">
            <div class="analytics-filter-field analytics-filter-period">
                <label for="period">Period</label>
                <select name="period" id="period" class="form-select">
                    <option value="today" @selected($filters['period'] === 'today')>Today</option>
                    <option value="7" @selected($filters['period'] === '7')>Last 7 Days</option>
                    <option value="30" @selected($filters['period'] === '30')>Last 30 Days</option>
                    <option value="month" @selected($filters['period'] === 'month')>This Month</option>
                    <option value="" @selected($filters['period'] === null)>Custom Range</option>
                </select>
            </div>
            <div class="analytics-filter-field">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $filters['start_date'] }}" max="{{ $filters['end_date'] }}">
            </div>
            <div class="analytics-filter-field">
                <label for="end_date">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $filters['end_date'] }}" min="{{ $filters['start_date'] }}">
            </div>
            <div class="analytics-filter-field analytics-filter-organization">
                <label for="organization_id">Organization</label>
                <select name="organization_id" id="organization_id" class="form-select">
                    <option value="">All Organizations</option>
                    @foreach ($organizationsList as $organization)
                        <option value="{{ $organization->id }}" @selected($filters['organization_id'] === $organization->id)>{{ $organization->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="analytics-filter-actions">
                <button type="submit" class="btn analytics-button analytics-button-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply Filter</button>
                <a class="btn analytics-button analytics-button-muted" href="{{ route('analytics') }}">Reset Filter</a>
            </div>
        </form>
        @if ($errors->any())
            <p class="analytics-filter-error" role="alert">{{ $errors->first() }}</p>
        @endif
        <p class="analytics-range-note">Showing {{ \Illuminate\Support\Carbon::parse($filters['start_date'])->format('M j, Y') }} to {{ \Illuminate\Support\Carbon::parse($filters['end_date'])->format('M j, Y') }}</p>
    </section>

    <section class="analytics-metrics" aria-label="Summary statistics">
        @foreach ($metricCards as $card)
            <article class="analytics-metric analytics-tone-{{ $card['tone'] }}">
                <div class="analytics-metric-top"><span>{{ $card['label'] }}</span><i class="bi {{ $card['icon'] }}" aria-hidden="true"></i></div>
                <strong>{{ number_format($card['value']) }}</strong>
            </article>
        @endforeach
    </section>

    <section class="analytics-chart-grid">
        <article class="analytics-panel analytics-trend-panel">
            <div class="analytics-panel-heading"><div><h2>QR Scan Activity</h2><p>Entries and exits by day</p></div><span class="analytics-period-tag">{{ $trend->count() }} days</span></div>
            @if ($cards['scans'] > 0)
                <div class="analytics-trend-chart" role="img" aria-label="Daily entry and exit scans for selected dates">
                    @foreach ($trend as $day)
                        <div class="analytics-trend-day" title="{{ $day['date'] }}: {{ $day['entries'] }} entries, {{ $day['exits'] }} exits">
                            <div class="analytics-trend-bars">
                                <span class="analytics-trend-bar analytics-entry-bar" style="height: {{ $day['entries'] ? max(3, round($day['entries'] / $maxTrend * 100)) : 0 }}%"></span>
                                <span class="analytics-trend-bar analytics-exit-bar" style="height: {{ $day['exits'] ? max(3, round($day['exits'] / $maxTrend * 100)) : 0 }}%"></span>
                            </div>
                            <span class="analytics-trend-label">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="analytics-legend"><span><i class="analytics-legend-entry"></i> Entries</span><span><i class="analytics-legend-exit"></i> Exits</span></div>
            @else
                <p class="analytics-empty">No attendance data available for the selected period.</p>
            @endif
        </article>

        <article class="analytics-panel analytics-comparison-panel">
            <div class="analytics-panel-heading"><div><h2>Entry vs Exit</h2><p>Selected date range</p></div></div>
            @if ($cards['scans'] > 0)
                @php($comparisonTotal = max(1, $entryExit['entries'] + $entryExit['exits']))
                <div class="analytics-comparison-total">{{ number_format($comparisonTotal) }} <span>recorded scans</span></div>
                <div class="analytics-comparison-track" role="img" aria-label="{{ $entryExit['entries'] }} entries and {{ $entryExit['exits'] }} exits">
                    <span class="analytics-comparison-entry" style="width: {{ round($entryExit['entries'] / $comparisonTotal * 100, 2) }}%"></span>
                    <span class="analytics-comparison-exit" style="width: {{ round($entryExit['exits'] / $comparisonTotal * 100, 2) }}%"></span>
                </div>
                <div class="analytics-comparison-values">
                    <div><span><i class="analytics-legend-entry"></i> Entries</span><strong>{{ number_format($entryExit['entries']) }}</strong></div>
                    <div><span><i class="analytics-legend-exit"></i> Exits</span><strong>{{ number_format($entryExit['exits']) }}</strong></div>
                </div>
            @else
                <p class="analytics-empty">No attendance data available for the selected period.</p>
            @endif
        </article>

        <article class="analytics-panel analytics-hours-panel">
            <div class="analytics-panel-heading"><div><h2>Peak Activity Hours</h2><p>Recorded scans by hour of day</p></div></div>
            @if ($cards['scans'] > 0)
                <div class="analytics-hour-chart" role="img" aria-label="Scan count by hour">
                    @foreach ($peakHours as $hour)
                        <div class="analytics-hour-column" title="{{ $hour['label'] }}: {{ $hour['scans'] }} scans">
                            <span class="analytics-hour-value">{{ $hour['scans'] ?: '' }}</span>
                            <span class="analytics-hour-bar" style="height: {{ $hour['scans'] ? max(3, round($hour['scans'] / $maxHour * 100)) : 0 }}%"></span>
                            <span class="analytics-hour-label">{{ $loop->first || $loop->last || $loop->iteration % 3 === 0 ? $hour['label'] : '' }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="analytics-empty">No attendance data available for the selected period.</p>
            @endif
        </article>

        <article class="analytics-panel analytics-organization-chart-panel">
            <div class="analytics-panel-heading"><div><h2>Organization Activity</h2><p>Recorded scans in selected range</p></div></div>
            @if ($organizations->isNotEmpty())
                <div class="analytics-organization-chart">
                    @foreach ($organizations as $organization)
                        <div class="analytics-organization-bar-row">
                            <span class="analytics-organization-bar-name" title="{{ $organization->name }}">{{ $organization->name }}</span>
                            <span class="analytics-organization-bar-track"><i style="width: {{ $organization->total_scans ? max(2, round($organization->total_scans / $maxOrganization * 100)) : 0 }}%"></i></span>
                            <strong>{{ number_format($organization->total_scans) }}</strong>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="analytics-empty">No organizations available.</p>
            @endif
        </article>
    </section>

    <section class="analytics-panel analytics-table-panel">
        <div class="analytics-panel-heading"><div><h2>Daily Driver Activity</h2><p>Newest dates first</p></div></div>
        @if ($dailyActivity->isNotEmpty())
            <div class="table-responsive">
                <table class="table analytics-table mb-0">
                    <thead><tr><th>Date</th><th>Entries</th><th>Exits</th><th>Total Scans</th></tr></thead>
                    <tbody>
                    @foreach ($dailyActivity as $day)
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, M j, Y') }}</td><td>{{ number_format($day['entries']) }}</td><td>{{ number_format($day['exits']) }}</td><td><strong>{{ number_format($day['entries'] + $day['exits']) }}</strong></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="analytics-empty">No attendance data available for the selected period.</p>
        @endif
        @if ($dailyActivity->hasPages())
            <div class="analytics-pagination">{{ $dailyActivity->links() }}</div>
        @endif
    </section>

    <section class="analytics-terminal-strip" aria-label="Current terminal status">
        <div><span>Drivers Currently Inside</span><strong>{{ number_format($cards['inside']) }}</strong></div>
        <div><span>Drivers Currently Outside</span><strong>{{ number_format($outside) }}</strong></div>
        <div><span>Entries Today</span><strong>{{ number_format($cards['today_entries']) }}</strong></div>
        <div><span>Exits Today</span><strong>{{ number_format($cards['today_exits']) }}</strong></div>
    </section>

    <section class="analytics-panel analytics-table-panel">
        <div class="analytics-panel-heading"><div><h2>Organization Records</h2><p>Driver, vehicle, and attendance totals</p></div></div>
        @if ($organizations->isNotEmpty())
            <div class="table-responsive">
                <table class="table analytics-table mb-0">
                    <thead><tr><th>Organization</th><th>Drivers</th><th>Vehicles</th><th>Entries</th><th>Exits</th><th>Total Scans</th></tr></thead>
                    <tbody>
                    @foreach ($organizations as $organization)
                        <tr><td><strong>{{ $organization->name }}</strong></td><td>{{ number_format($organization->drivers_count) }}</td><td>{{ number_format($organization->vehicles_count) }}</td><td>{{ number_format($organization->entries) }}</td><td>{{ number_format($organization->exits) }}</td><td>{{ number_format($organization->total_scans) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="analytics-empty">No organizations available.</p>
        @endif
    </section>

    <section class="analytics-panel analytics-table-panel">
        <div class="analytics-panel-heading"><div><h2>Driver Activity</h2><p>Attendance totals for the selected date range</p></div><span class="analytics-period-tag">{{ number_format($drivers->total()) }} drivers</span></div>
        @if ($drivers->count())
            <div class="table-responsive">
                <table class="table analytics-table mb-0">
                    <thead><tr><th>Driver</th><th>Organization</th><th>Vehicle</th><th>Entries</th><th>Exits</th><th>Total Scans</th><th>Last Scan</th><th>Current Status</th></tr></thead>
                    <tbody>
                    @foreach ($drivers as $driver)
                        @php($status = $driver->latest_attendance_type === 'ENTRY' ? 'Inside' : ($driver->latest_attendance_type === 'EXIT' ? 'Outside' : 'No Activity'))
                        <tr>
                            <td><strong>{{ $driver->name }}</strong></td>
                            <td>{{ $driver->organization?->name ?? 'Not assigned' }}</td>
                            <td>{{ $driver->vehicle?->plate_number ?? $driver->vehicle_number ?? 'Unassigned' }}</td>
                            <td>{{ number_format($driver->analytics_entries) }}</td>
                            <td>{{ number_format($driver->analytics_exits) }}</td>
                            <td>{{ number_format($driver->analytics_total_scans) }}</td>
                            <td>{{ $driver->last_scanned_at ? \Illuminate\Support\Carbon::parse($driver->last_scanned_at)->format('M j, Y g:i A') : 'Never' }}</td>
                            <td><span class="analytics-status analytics-status-{{ \Illuminate\Support\Str::slug($status) }}">{{ $status }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="analytics-pagination">{{ $drivers->links() }}</div>
        @else
            <p class="analytics-empty">No drivers found for this organization.</p>
        @endif
    </section>

    <div class="analytics-loading" id="analytics-loading" hidden role="status" aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading analytics data…</div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('analytics-filter-form');
            const period = document.getElementById('period');
            const start = document.getElementById('start_date');
            const end = document.getElementById('end_date');
            const loading = document.getElementById('analytics-loading');
            const refresh = document.getElementById('analytics-refresh');

            period.addEventListener('change', () => {
                start.value = '';
                end.value = '';
                if (period.value) {
                    loading.hidden = false;
                    form.requestSubmit();
                }
            });
            document.getElementById('organization_id').addEventListener('change', () => {
                loading.hidden = false;
                form.requestSubmit();
            });
            start.addEventListener('change', () => { period.value = ''; end.min = start.value; });
            end.addEventListener('change', () => { period.value = ''; start.max = end.value; });
            form.addEventListener('submit', () => { loading.hidden = false; });
            refresh.addEventListener('click', () => { loading.hidden = false; });
        })();
    </script>
@endpush
