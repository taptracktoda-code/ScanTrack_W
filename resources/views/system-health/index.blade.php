@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Operations monitoring</p>
                <h2 class="dashboard-page-title mb-2">System Health</h2>
                <p class="dashboard-page-subtitle mb-0">Checks this application endpoint, database queries, and local storage every 5 seconds while this page is visible.</p>
            </div>
            <div class="dashboard-pill-group d-flex flex-wrap gap-2">
                <span class="dashboard-mini-pill"><i class="bi bi-arrow-repeat me-2"></i>Auto-refresh: 5s</span>
                <button id="refresh-system-health" class="dashboard-mini-pill border-0" type="button">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Refresh
                </button>
            </div>
        </div>
    </section>

    <section class="welcome-card card border-0 text-white">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <p class="welcome-eyebrow mb-1">Overall System Status</p>
                    <h2 class="welcome-name mb-0" id="overall-status-label">Checking system status...</h2>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="dashboard-mini-pill text-dark" id="overall-status-pill">
                        <i class="bi bi-shield-check me-2"></i>
                        Loading...
                    </div>
                    <button id="system-health-action-btn" class="btn btn-secondary" type="button">
                        <i class="bi bi-broadcast-pin me-2"></i>
                        System Check
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-3">
        <p id="system-health-freshness" class="text-muted mb-2" role="status" aria-live="polite">Waiting for the first check...</p>
        <p class="text-muted small">These checks cover this app and its local storage; host resources and external services require separate monitoring.</p>
        <div id="system-health-reasons" class="d-flex flex-wrap gap-2"></div>
    </section>

    <section class="card dashboard-card border-0 mt-4">
        <div class="card-body p-4">
            <div id="system-health-loading" class="text-muted">
                <div class="d-flex align-items-center gap-2">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <span>Loading system health data...</span>
                </div>
            </div>

            <div id="system-health-error" class="alert alert-danger d-none" role="alert"></div>

            <div id="system-health-content" class="row g-3 d-none">
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Application Response</div>
                            <div class="stat-value" id="server-status">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Database Connection</div>
                            <div class="stat-value" id="database-status">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Health Endpoint</div>
                            <div class="stat-value" id="api-status">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Local Storage Read / Write / Delete</div>
                            <div class="stat-value" id="storage-status">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Total Registered Drivers</div>
                            <div class="stat-value" id="drivers-count">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Total Registered Vehicles</div>
                            <div class="stat-value" id="vehicles-count">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Total Registered QR Codes</div>
                            <div class="stat-value" id="qr-codes-count">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Today's Attendance Count</div>
                            <div class="stat-value" id="attendance-count">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Total Attendance Logs</div>
                            <div class="stat-value" id="total-logs-count">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Last QR Scan</div>
                            <div class="mt-3 row g-3">
                                <div class="col-md-4">
                                    <div class="text-muted small">Driver</div>
                                    <div class="fw-bold" id="last-scan-driver">-</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Attendance Type</div>
                                    <div class="fw-bold" id="last-scan-type">-</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Date &amp; Time</div>
                                    <div class="fw-bold" id="last-scan-time">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/system-health.js') }}" data-system-health-url="{{ route('system-health.api') }}" defer></script>
@endpush
