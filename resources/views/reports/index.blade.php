@extends('layouts.app')

@push('styles')
    <link href="{{ asset('css/reports.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php
        $monthlyRows = collect($monthlyPerformance);
        $monthlyPeak = (int) ($monthlyRows->max('count') ?? 0);
        $chartScale = max(1, $monthlyPeak);
        $chartMidpoint = (int) ceil($monthlyPeak / 2);
        $metricIcons = [
            'Total Drivers' => 'bi-people-fill',
            'Active Drivers' => 'bi-person-check-fill',
            'Assigned Vehicles' => 'bi-truck-front-fill',
        ];
    @endphp

    <main class="reports-page">
        <header class="reports-header">
            <div>
                <p class="reports-eyebrow">SCANTRACK / OPERATIONS</p>
                <h1 class="reports-title">Reports</h1>
                <p class="reports-description">Driver records, registrations, and terminal attendance exports.</p>
            </div>
            <a class="btn reports-primary-action" href="{{ route('reports.export', ['type' => 'driver-summary']) }}">
                <i class="bi bi-download" aria-hidden="true"></i>
                <span>Export driver summary</span>
            </a>
        </header>

        <section class="reports-metrics" aria-label="Driver summary">
            @foreach ($reportCards as $card)
                <article class="reports-metric">
                    <div class="reports-metric-heading">
                        <span>{{ $card['label'] }}</span>
                        <i class="bi {{ $metricIcons[$card['label']] ?? 'bi-bar-chart' }}" aria-hidden="true"></i>
                    </div>
                    <strong class="reports-metric-value">{{ $card['value'] }}</strong>
                    <span class="reports-metric-note">{{ $card['trend'] }}</span>
                </article>
            @endforeach
        </section>

        <section class="reports-panels">
            <article class="reports-panel reports-chart-panel">
                <div class="reports-panel-heading">
                    <div>
                        <h2>Driver registrations</h2>
                        <p>New driver records over the last five months</p>
                    </div>
                    <span class="reports-panel-tag">5 months</span>
                </div>

                @if ($monthlyPeak > 0)
                    <div class="reports-chart" role="img" aria-label="Monthly driver registrations, peak {{ $monthlyPeak }}">
                        <div class="reports-chart-axis" aria-hidden="true">
                            <span>{{ number_format($monthlyPeak) }}</span>
                            <span>{{ number_format($chartMidpoint) }}</span>
                            <span>0</span>
                        </div>
                        <div class="reports-chart-columns">
                            @foreach ($monthlyRows as $month)
                                <div class="reports-chart-column" title="{{ $month['label'] }}: {{ $month['count'] }} drivers">
                                    <div class="reports-bar-track">
                                        <div class="reports-bar-stack">
                                            <span class="reports-bar-value">{{ $month['count'] }}</span>
                                            <span class="reports-bar" style="height: {{ max(4, round($month['count'] / $chartScale * 100)) }}%"></span>
                                        </div>
                                    </div>
                                    <span class="reports-month-label">{{ $month['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="reports-empty-chart">
                        <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                        <span>No new driver registrations in this period.</span>
                    </div>
                @endif
            </article>

            <article class="reports-panel reports-exports-panel">
                <div class="reports-panel-heading">
                    <div>
                        <h2>Available exports</h2>
                        <p>Download current records as CSV files</p>
                    </div>
                </div>
                <div class="reports-export-list">
                    @foreach ($summaryReports as $report)
                        <div class="reports-export-row">
                            <span class="reports-export-icon"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></span>
                            <div class="reports-export-copy">
                                <strong>{{ $report['title'] }}</strong>
                                <span>{{ number_format($report['count']) }} records · {{ $report['type'] }}</span>
                            </div>
                            <a class="btn reports-download-action" href="{{ route('reports.export', ['type' => $report['export_type']]) }}" aria-label="Download {{ $report['title'] }}">
                                <i class="bi bi-download" aria-hidden="true"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="reports-table-panel">
            <div class="reports-table-heading">
                <div>
                    <h2>Driver records</h2>
                    <p>Current driver details included in the summary export.</p>
                </div>
                <span class="reports-record-count"><i class="bi bi-people" aria-hidden="true"></i>{{ number_format($drivers->count()) }} drivers</span>
            </div>
            <div class="table-responsive">
                <table class="table reports-table align-middle mb-0">
                    <caption class="visually-hidden">Driver records including license, contact, vehicle, status, and last updated date</caption>
                    <thead>
                        <tr>
                            <th scope="col">Driver</th>
                            <th scope="col">License</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Plate number</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr>
                                <td class="reports-driver-name">{{ $driver->name ?: 'Unnamed Driver' }}</td>
                                <td>{{ $driver->license_number ?: 'Not set' }}</td>
                                <td>{{ $driver->contact_number ?: 'Not set' }}</td>
                                <td class="reports-plate">{{ $driver->vehicle_number ?: 'Unassigned' }}</td>
                                <td><span class="driver-status-badge {{ $driver->status === 'Active' ? 'is-active' : 'is-suspended' }}">{{ $driver->status }}</span></td>
                                <td>{{ optional($driver->updated_at)->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="reports-table-empty">No driver records found. Register a driver to populate reports.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
