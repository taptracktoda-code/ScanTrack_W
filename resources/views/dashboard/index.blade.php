@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Terminal operations</p>
                
            </div>
            <div class="dashboard-pill-group d-flex flex-wrap gap-2">
                <span class="dashboard-mini-pill"><i class="bi bi-calendar3 me-2"></i>{{ now()->format('F j, Y') }}</span>
                <a id="dashboard-system-health-pill" href="{{ route('system-health') }}" class="dashboard-mini-pill text-decoration-none" role="button" aria-label="System Health">
                    <i class="bi bi-shield-check me-2"></i>
                    System Health
                </a>
            </div>
        </div>
    </section>

    <section class="welcome-card card border-0 text-white">
        <div class="card-body p-3 p-lg-4">
            <div class="row align-items-start g-2 g-lg-3">
                <div class="col-lg-7">
                    <p class="welcome-eyebrow mb-0" id="dashboard-greeting">{{ now()->hour < 12 ? 'Good morning,' : (now()->hour < 18 ? 'Good afternoon,' : 'Good evening,') }}</p>
                    <h2 class="welcome-name mb-0">{{ auth()->user()->name ?? 'Juan Dela Cruz' }}</h2>
                    <p class="welcome-meta mb-1">
                        User ID: {{ auth()->user()->user_id ?? 'TODA-2026-014' }}
                        &nbsp;•&nbsp;
                        Role: {{ auth()->user()->role ?? 'User' }}
                        &nbsp;•&nbsp;
                        Assigned Unit: {{ auth()->user()->unit ?? 'Main unit' }}
                    </p>
                    <div class="welcome-summary d-flex flex-wrap gap-2">
                        <div>
                            <div class="summary-label">Active Drivers</div>
                            <div class="summary-value" data-dashboard-summary="active_drivers">{{ number_format($stats['active_drivers']['value']) }}</div>
                        </div>
                        <div>
                            <div class="summary-label">Pending Logs</div>
                            <div class="summary-value" data-dashboard-summary="pending_logs">{{ number_format($stats['pending_logs']['value'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                @if (auth()->user()?->isSuperAdmin())
                    <div class="col-lg-5">
                        <label for="dashboard-driver-search" class="form-label text-white-50 mb-2 fw-semibold">Search driver</label>
                        <div class="position-relative">
                            <div class="input-group input-group-lg search-driver-wrap">
                                <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-search"></i></span>
                                <input id="dashboard-driver-search" type="search" class="form-control border-0" placeholder="Search by name or ID" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="dashboard-driver-search-results" aria-expanded="false">
                            </div>
                            <div id="dashboard-driver-search-results" class="list-group position-absolute top-100 start-0 w-100 shadow mt-1 d-none" role="listbox" style="z-index: 20; max-height: 20rem; overflow-y: auto;"></div>
                            <div id="dashboard-driver-search-status" class="small text-white-50 mt-2" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-4">
        <div class="row g-3 g-xl-4">
            @php
                $statRouteMap = [
                    'organizations' => route('settings.toda_organizations'),
                    'vehicles' => route('vehicles'),
                    'drivers' => route('drivers'),
                    'active_drivers' => route('drivers'),
                    'managers' => route('managers'),
                    'driver_logs_today' => route('pending.logs'),
                ];
            @endphp

            @foreach ($stats as $key => $stat)
                @continue($key === 'pending_logs')
                <div class="col-sm-6 col-xl-3">
                    @if (isset($statRouteMap[$key]))
                        <a href="{{ $statRouteMap[$key] }}" class="stat-card card border-0 h-100 text-decoration-none text-reset" data-dashboard-stat="{{ $key }}">
                            <div class="card-body p-4">
                                <div class="stat-top">
                                    <div class="stat-icon">
                                        <i class="bi {{ $stat['icon'] }}"></i>
                                    </div>
                                </div>
                                <div class="stat-value mt-3" data-stat-value>{{ number_format($stat['value']) }}</div>
                                <div class="stat-label">{{ $stat['label'] }}</div>
                            </div>
                        </a>
                    @else
                        <div class="stat-card card border-0 h-100" data-dashboard-stat="{{ $key }}">
                            <div class="card-body p-4">
                                <div class="stat-top">
                                    <div class="stat-icon">
                                        <i class="bi {{ $stat['icon'] }}"></i>
                                    </div>
                                </div>
                                <div class="stat-value mt-3" data-stat-value>{{ number_format($stat['value']) }}</div>
                                <div class="stat-label">{{ $stat['label'] }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="card dashboard-card border-0 mt-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h3 class="section-title mb-1">TODA Organizations</h3>
                    <p class="section-subtitle mb-0">Organizations registered for terminal operations.</p>
                </div>
                <a href="{{ route('settings.toda_organizations') }}" class="btn btn-light">Manage organizations</a>
            </div>
            <div class="table-responsive">
                <table class="table driver-table align-middle mb-0">
                    <thead><tr><th>Organization</th><th>Barangay / Travel Area</th></tr></thead>
                    <tbody>
                        @forelse ($organizations as $organization)
                            <tr><td class="fw-bold">{{ $organization->name }}</td><td>{{ $organization->barangay_travel_area }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4 text-muted">No organizations registered.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card dashboard-card border-0 mt-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h3 class="section-title mb-1">Recent QR Scans</h3>
                    <p class="section-subtitle mb-0">Latest recorded driver entries and exits.</p>
                </div>
                <a href="{{ route('attendance') }}" class="btn btn-light">View attendance</a>
            </div>
            <div class="table-responsive">
                <table class="table driver-table align-middle mb-0">
                    <thead><tr><th>Time</th><th>Driver</th><th>Plate</th><th>Organization</th><th>Scan</th></tr></thead>
                    <tbody>
                        @forelse ($recentScans as $scan)
                            <tr>
                                <td>{{ optional($scan->scanned_at ?: $scan->created_at)->format('M j, Y H:i:s') }}</td>
                                <td class="fw-bold">{{ $scan->driver?->name ?: 'Unknown driver' }}</td>
                                <td>{{ $scan->driver?->vehicle_number ?: 'Unassigned' }}</td>
                                <td>{{ $scan->driver?->organization?->name ?: 'Not assigned' }}</td>
                                <td>{{ $scan->attendance_type ?: 'UNKNOWN' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">No scans recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card dashboard-card border-0 mt-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h3 class="section-title mb-1">Recent System Activity</h3>
                    <p class="section-subtitle mb-0">Account, verification, and terminal events across the system.</p>
                </div>
                <a href="{{ route('terminal.activities') }}" class="btn btn-light">View activity log</a>
            </div>
            <div class="table-responsive">
                <table class="table driver-table align-middle mb-0">
                    <thead><tr><th>Time</th><th>Activity</th><th>Account</th><th>Details</th></tr></thead>
                    <tbody>
                        @forelse ($recentActivities as $activity)
                            <tr>
                                <td>{{ $activity->created_at?->diffForHumans() }}</td>
                                <td class="fw-bold">{{ $activity->activity }}</td>
                                <td>{{ $activity->driver?->name ?? $activity->admin?->name ?? 'System' }}</td>
                                <td>{{ $activity->description ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">No system activity recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

@endsection

@push('scripts')
    <script>
        (() => {
            const statCards = document.querySelectorAll('[data-dashboard-stat]');
            const endpoint = @json(route('dashboard.stats'));
            const driverSearchEndpoint = @json(route('drivers.search'));
            const formatter = new Intl.NumberFormat();
            const greetingElement = document.getElementById('dashboard-greeting');

            const driverSearchInput = document.getElementById('dashboard-driver-search');
            const driverSearchResults = document.getElementById('dashboard-driver-search-results');
            const driverSearchStatus = document.getElementById('dashboard-driver-search-status');
            let driverSearchTimer;
            let driverSearchController;

            const hideDriverSearchResults = () => {
                driverSearchResults?.classList.add('d-none');
                driverSearchInput?.setAttribute('aria-expanded', 'false');
            };

            const renderDriverSearchResults = (drivers) => {
                if (!driverSearchResults) return;

                driverSearchResults.replaceChildren();
                driverSearchStatus.textContent = drivers.length ? `${drivers.length} matching driver${drivers.length === 1 ? '' : 's'}` : 'No matching drivers found.';

                drivers.forEach((driver) => {
                    const item = document.createElement('a');
                    item.className = 'list-group-item list-group-item-action text-decoration-none';
                    item.href = driver.url;
                    item.setAttribute('role', 'option');

                    const heading = document.createElement('div');
                    heading.className = 'd-flex align-items-center justify-content-between gap-2';
                    const name = document.createElement('span');
                    name.className = 'fw-semibold';
                    name.textContent = driver.name || 'Unnamed driver';
                    const status = document.createElement('span');
                    status.className = `badge ${driver.status === 'Active' ? 'text-bg-success' : 'text-bg-secondary'}`;
                    status.textContent = driver.status || 'Unknown';
                    heading.append(name, status);

                    const details = document.createElement('div');
                    details.className = 'small text-muted mt-1';
                    details.textContent = [driver.driver_id, driver.vehicle_number || 'No plate', driver.organization || 'No organization'].join(' · ');
                    item.append(heading, details);
                    driverSearchResults.append(item);
                });

                driverSearchResults.classList.remove('d-none');
                driverSearchInput.setAttribute('aria-expanded', 'true');
            };

            const searchDrivers = async (term) => {
                if (!term.trim()) {
                    driverSearchResults?.replaceChildren();
                    driverSearchStatus.textContent = '';
                    hideDriverSearchResults();
                    return;
                }

                driverSearchController?.abort();
                driverSearchController = new AbortController();
                driverSearchStatus.textContent = 'Searching drivers...';

                try {
                    const url = new URL(driverSearchEndpoint, window.location.origin);
                    url.searchParams.set('q', term.trim());
                    const response = await fetch(url, {
                        signal: driverSearchController.signal,
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });

                    if (!response.ok) throw new Error('Driver search request failed.');
                    const payload = await response.json();
                    if (driverSearchInput.value.trim() === term.trim()) {
                        renderDriverSearchResults(payload.drivers || []);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        driverSearchStatus.textContent = 'Driver search is unavailable right now.';
                    }
                }
            };

            driverSearchInput?.addEventListener('input', () => {
                window.clearTimeout(driverSearchTimer);
                driverSearchTimer = window.setTimeout(() => searchDrivers(driverSearchInput.value), 250);
            });

            driverSearchInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') hideDriverSearchResults();
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('#dashboard-driver-search') && !event.target.closest('#dashboard-driver-search-results')) {
                    hideDriverSearchResults();
                }
            });

            const getGreeting = () => {
                const hour = new Date().getHours();

                if (hour < 12) {
                    return 'Good morning,';
                }

                if (hour < 18) {
                    return 'Good afternoon,';
                }

                return 'Good evening,';
            };

            const updateGreeting = () => {
                if (greetingElement) {
                    greetingElement.textContent = getGreeting();
                }
            };

            const updateValue = (element, value) => {
                if (!element) {
                    return;
                }

                const nextValue = formatter.format(Number(value) || 0);
                if (element.textContent.trim() !== nextValue) {
                    element.textContent = nextValue;
                }
            };

            const refreshDashboardStats = async () => {
                try {
                    const response = await fetch(endpoint, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    Object.entries(payload.stats || {}).forEach(([key, stat]) => {
                        const card = document.querySelector(`[data-dashboard-stat="${key}"]`);
                        updateValue(card?.querySelector('[data-stat-value]'), stat.value);
                        updateValue(document.querySelector(`[data-dashboard-summary="${key}"]`), stat.value);
                    });
                } catch (error) {
                    console.warn('Unable to refresh dashboard stats.', error);
                }
            };

            updateGreeting();

            if (statCards.length) {
                refreshDashboardStats();
                window.setInterval(refreshDashboardStats, 5000);
            }

            window.setInterval(updateGreeting, 60000);

            // Fetch system health and update the dashboard System Health pill color
            const systemHealthPill = document.getElementById('dashboard-system-health-pill');
            const updateSystemHealthPill = async () => {
                if (!systemHealthPill) return;

                try {
                    const res = await fetch('{{ route('system-health.api') }}', {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });

                    if (!res.ok) return;

                    const data = await res.json();
                    const status = (data?.overall_status || 'Healthy').toString();

                    systemHealthPill.classList.remove('bg-success', 'bg-danger', 'text-white', 'text-dark');

                    if (status === 'Healthy') {
                        systemHealthPill.classList.add('bg-success', 'text-white');
                    } else {
                        systemHealthPill.classList.add('bg-danger', 'text-white');
                    }
                } catch (e) {
                    // keep pill neutral on error
                    console.warn('Unable to fetch system health for pill update.', e);
                }
            };

            updateSystemHealthPill();
            window.setInterval(updateSystemHealthPill, 300000); // refresh every 5 minutes

        })();
    </script>
@endpush
