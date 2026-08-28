@extends('layouts.app')

@section('content')
    <section class="dashboard-hero report-hero mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
           
            <a class="btn driver-primary-button report-export-button" href="{{ route('reports.export', ['type' => 'driver-summary']) }}">
                <i class="bi bi-plus-lg me-2"></i>
                Export Report
            </a>
        </div>
    </section>

    <section class="row g-3 g-xl-4 mb-4">
        @foreach ($reportCards as $card)
            <div class="col-md-4">
                <div class="report-metric-card">
                    <div class="report-metric-label">{{ $card['label'] }}</div>
                    <div class="report-metric-value">{{ $card['value'] }}</div>
                    <div class="report-metric-trend">{{ $card['trend'] }}</div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="report-performance-card mb-3">
        <h3 class="report-widget-title">Monthly Performance</h3>
        <div class="report-chart-wrap">
            <div class="report-chart-axis">
                <span>2000</span>
                <span>1800</span>
            </div>
            <div class="report-bar-chart" aria-label="Monthly performance chart">
                @foreach ($monthlyPerformance as $month)
                    <span class="report-chart-bar-wrap">
                        <span class="report-chart-bar" style="--bar-height: {{ $month['height'] }}%;" title="{{ $month['count'] }} drivers"></span>
                        <span class="report-chart-label">{{ $month['label'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    <section class="report-summary-panel">
        <h3 class="report-widget-title mb-3">Summary Report</h3>
        <div class="report-list">
            @foreach ($summaryReports as $report)
                <div class="report-list-row">
                    <div class="report-list-main">
                        <i class="bi bi-file-earmark-text report-file-icon"></i>
                        <span>{{ $report['title'] }} <small>({{ $report['count'] }} records)</small></span>
                    </div>
                    <span class="report-file-type">{{ $report['type'] }}</span>
                    <a class="btn report-download-btn" href="{{ route('reports.export', ['type' => $report['export_type']]) }}" aria-label="Download {{ $report['title'] }}">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="driver-table-card report-driver-card mt-3">
        <div class="report-table-toolbar">
            <div>
                <h3 class="section-title mb-1">Driver Database Records</h3>
                <p class="section-subtitle mb-0">Showing live records from the drivers table.</p>
            </div>
            <span class="dashboard-mini-pill">
                <i class="bi bi-people me-2"></i>
                {{ $drivers->count() }} drivers
            </span>
        </div>
        <div class="table-responsive">
            <table class="table driver-table report-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Driver name</th>
                        <th>License No.</th>
                        <th>Contact</th>
                        <th>Vehicle num.</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drivers as $driver)
                        <tr>
                            <td class="fw-bold">{{ $driver->name ?: 'Unnamed Driver' }}</td>
                            <td>{{ $driver->license_number ?: 'Not set' }}</td>
                            <td>{{ $driver->contact_number ?: 'Not set' }}</td>
                            <td class="fw-bold">{{ $driver->vehicle_number ?: 'Unassigned' }}</td>
                            <td>
                                <span class="driver-status-badge {{ $driver->status === 'Active' ? 'is-active' : 'is-suspended' }}">
                                    {{ $driver->status }}
                                </span>
                            </td>
                            <td>{{ optional($driver->updated_at)->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No driver records found. Add drivers first to generate a report.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
