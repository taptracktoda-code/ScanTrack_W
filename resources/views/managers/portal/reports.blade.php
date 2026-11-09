@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Organization analytics</p>
            <h1 class="dashboard-page-title mb-2">Reports</h1>
            <p class="dashboard-page-subtitle mb-0">{{ $organization->name }} · {{ now()->format('F j, Y') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('manager.reports.export') }}">
            <i class="bi bi-download me-2"></i>Download CSV
        </a>
    </section>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card dashboard-card border-0 shadow-sm mb-4" id="driver-reports" data-reports-inbox>
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Driver reports</h2>
                <button class="btn btn-outline-primary btn-sm" type="button" data-refresh-reports>Refresh reports</button>
            </div>
            <form method="GET" action="{{ route('manager.reports') }}" class="row g-3 mb-3" data-loading-form>
                <div class="col-md-5"><label for="report-search" class="form-label">Search driver or report details</label><input id="report-search" class="form-control" name="search" maxlength="200" value="{{ request('search') }}"></div>
                <div class="col-md-3"><label for="report-status" class="form-label">Review status</label><select id="report-status" class="form-select" name="status"><option value="">All statuses</option>@foreach (['pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label for="report-date" class="form-label">Submission date (Manila)</label><input id="report-date" type="date" class="form-control" name="date" value="{{ request('date') }}"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit" data-busy-label="Loading reports...">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('manager.reports') }}">Clear</a></div>
            </form>
            <p class="small text-muted" role="status" aria-live="polite" data-report-sync>Reports refresh automatically every 30 seconds.</p>
            <div class="alert alert-danger d-none" role="alert" data-report-error>Unable to refresh reports. The list may be out of date. Check your connection and select Refresh reports to retry.</div>
            <div data-report-results>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Driver</th><th>Submitted (Manila)</th><th>Report details</th><th>Status</th><th>Attachments</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>@forelse ($reports as $report)
                        <tr>
                            <td>{{ $report->driver?->name ?? 'Deleted driver' }}</td>
                            <td>{{ $report->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($report->explanation, 120) }}</td>
                            <td><span class="badge {{ $report->status === 'pending' ? 'bg-warning text-dark' : ($report->status === 'approved' ? 'bg-success' : 'bg-danger') }}">{{ ucfirst($report->status) }}</span></td>
                            <td>{{ count($report->attachments ?? []) }}</td>
                            <td><a class="btn btn-outline-primary btn-sm" href="{{ route('manager.reports.show', $report) }}">Open report #{{ $report->id }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No submitted reports match your filters.</td></tr>
                    @endforelse</tbody>
                </table></div>
                {{ $reports->links() }}
            </div>
        </div>
    </section>
    <div class="row g-3">
        @foreach ([
            'Total drivers' => $totalDrivers,
            'Active drivers' => $activeDrivers,
            'Vehicles' => $totalVehicles,
            'Entries today' => $todayEntries,
            'Exits today' => $todayExits,
        ] as $label => $value)
            <div class="col-6 col-xl-4">
                <div class="card dashboard-card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-bold">{{ number_format($value) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/manager-reports.js') }}" defer></script>
@endpush
