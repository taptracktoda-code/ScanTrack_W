@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Operations monitoring</p>
                <h2 class="dashboard-page-title mb-2">System Health</h2>
                <p class="dashboard-page-subtitle mb-0">Review server, database, storage, and attendance health at a glance.</p>
            </div>
            <div class="dashboard-pill-group d-flex flex-wrap gap-2">
                <span class="dashboard-mini-pill"><i class="bi bi-calendar3 me-2"></i>{{ now()->format('F j, Y') }}</span>
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
                    <button id="system-health-action-btn" class="btn btn-success" type="button">
                        <i class="bi bi-broadcast-pin me-2"></i>
                        System Check
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-3">
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
                            <div class="stat-label">Server Status</div>
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
                            <div class="stat-label">Laravel API Status</div>
                            <div class="stat-value" id="api-status">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stat-card card border-0 h-100">
                        <div class="card-body p-4">
                            <div class="stat-label">Storage Status</div>
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
                            <div class="stat-label">Pending Logs Count</div>
                            <div class="stat-value" id="pending-logs-count">-</div>
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
    <script>
        (() => {
            const loadingElement = document.getElementById('system-health-loading');
            const errorElement = document.getElementById('system-health-error');
            const contentElement = document.getElementById('system-health-content');
            const refreshButton = document.getElementById('refresh-system-health');
            const statusLabel = document.getElementById('overall-status-label');
            const statusPill = document.getElementById('overall-status-pill');
            const actionButton = document.getElementById('system-health-action-btn');

            const setStatus = (status) => {
                const label = status || 'Healthy';
                statusLabel.textContent = `Overall System Status: ${label}`;
                statusPill.innerHTML = `<i class="bi ${label === 'Healthy' ? 'bi-shield-check' : label === 'Warning' ? 'bi-exclamation-triangle' : 'bi-shield-exclamation'} me-2"></i>${label}`;
                statusPill.className = 'dashboard-mini-pill text-dark';

                if (label === 'Healthy') {
                    statusPill.classList.add('bg-success', 'text-white');
                    actionButton.className = 'btn btn-success';
                    actionButton.innerHTML = '<i class="bi bi-broadcast-pin me-2"></i>All Systems Good';
                } else if (label === 'Warning') {
                    statusPill.classList.add('bg-warning', 'text-dark');
                    actionButton.className = 'btn btn-danger';
                    actionButton.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Needs Attention';
                } else {
                    statusPill.classList.add('bg-danger', 'text-white');
                    actionButton.className = 'btn btn-danger';
                    actionButton.innerHTML = '<i class="bi bi-shield-exclamation me-2"></i>Critical Issue';
                }
            };

            const renderHealth = (data) => {
                document.getElementById('server-status').textContent = data.server;
                document.getElementById('database-status').textContent = data.database;
                document.getElementById('api-status').textContent = data.api;
                document.getElementById('storage-status').textContent = data.storage;
                document.getElementById('drivers-count').textContent = data.drivers;
                document.getElementById('vehicles-count').textContent = data.vehicles;
                document.getElementById('qr-codes-count').textContent = data.qr_codes;
                document.getElementById('attendance-count').textContent = data.today_attendance;
                document.getElementById('pending-logs-count').textContent = data.pending_logs;
                document.getElementById('last-scan-driver').textContent = data.last_scan?.driver || 'N/A';
                document.getElementById('last-scan-type').textContent = data.last_scan?.type || 'N/A';
                document.getElementById('last-scan-time').textContent = data.last_scan?.time || 'N/A';
                setStatus(data.overall_status);

                // render reasons
                const reasonsContainer = document.getElementById('system-health-reasons');
                if (reasonsContainer) {
                    reasonsContainer.innerHTML = '';
                    const reasons = data.reasons || [];
                    if (reasons.length === 0) {
                        const span = document.createElement('div');
                        span.className = 'dashboard-mini-pill';
                        span.textContent = 'No issues detected';
                        reasonsContainer.appendChild(span);
                    } else {
                        reasons.forEach((r) => {
                            const badge = document.createElement('div');
                            badge.className = 'dashboard-mini-pill';
                            badge.style.border = '1px solid rgba(0,0,0,0.06)';
                            badge.textContent = r;
                            if ((data.overall_status || '').toLowerCase() === 'critical') {
                                badge.classList.add('bg-danger', 'text-white');
                            } else {
                                badge.classList.add('bg-warning', 'text-dark');
                            }
                            reasonsContainer.appendChild(badge);
                        });
                    }
                }
            };

            const loadHealth = async () => {
                loadingElement.classList.remove('d-none');
                contentElement.classList.add('d-none');
                errorElement.classList.add('d-none');
                refreshButton.disabled = true;

                try {
                    const response = await fetch('{{ route('system-health.api') }}', {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load system health data.');
                    }

                    const data = await response.json();
                    renderHealth(data);
                    contentElement.classList.remove('d-none');
                } catch (error) {
                    errorElement.textContent = error.message || 'Unable to retrieve health information right now.';
                    errorElement.classList.remove('d-none');
                } finally {
                    loadingElement.classList.add('d-none');
                    refreshButton.disabled = false;
                }
            };

            refreshButton.addEventListener('click', loadHealth);
            loadHealth();
        })();
    </script>
@endpush
