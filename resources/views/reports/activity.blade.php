@extends('layouts.app')

@section('content')
    <section class="dashboard-hero report-hero mb-4">
        <div>
            <h1 class="dashboard-page-title mb-1">{{ $title }}</h1>
            <p class="dashboard-page-subtitle mb-0">{{ $description }}</p>
        </div>
    </section>

    @if ($mode === 'attendance')
        <section class="card dashboard-card border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('attendance') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="attendance-organization" class="form-label">Organization</label>
                        <select id="attendance-organization" name="organization_id" class="form-select">
                            <option value="">All organizations</option>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->id }}" @selected(($filters['organization_id'] ?? '') == $organization->id)>{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="attendance-driver" class="form-label">Driver</label>
                        <select id="attendance-driver" name="driver_id" class="form-select">
                            <option value="">All drivers</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected(($filters['driver_id'] ?? '') == $driver->id)>{{ $driver->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-xl-2">
                        <label for="attendance-type" class="form-label">Entry / Exit</label>
                        <select id="attendance-type" name="attendance_type" class="form-select">
                            <option value="">All scans</option>
                            <option value="ENTRY" @selected(($filters['attendance_type'] ?? '') === 'ENTRY')>Entry</option>
                            <option value="EXIT" @selected(($filters['attendance_type'] ?? '') === 'EXIT')>Exit</option>
                        </select>
                    </div>
                    <div class="col-6 col-xl-1">
                        <label for="attendance-start" class="form-label">From</label>
                        <input id="attendance-start" type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-6 col-xl-1">
                        <label for="attendance-end" class="form-label">To</label>
                        <input id="attendance-end" type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-6 col-xl-2 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary" href="{{ route('attendance') }}" aria-label="Clear filters" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <section class="driver-table-card">
        <div class="table-responsive">
            <table class="table driver-table align-middle mb-0">
                <thead>
                    @if ($mode === 'attendance')
                        <tr><th>Scanned</th><th>Driver</th><th>Vehicle</th><th>Organization</th><th>Type</th><th>Method</th><th>Terminal</th></tr>
                    @else
                        <tr><th>Time</th><th>Activity</th><th>Driver</th><th>Organization</th><th>Admin</th><th>Details</th></tr>
                    @endif
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @if ($mode === 'attendance')
                            <tr>
                                <td>{{ optional($record->scanned_at ?: $record->created_at)->format('M j, Y H:i:s') }}</td>
                                <td class="fw-bold">{{ $record->driver?->name ?: 'Unknown driver' }}</td>
                                <td>{{ $record->driver?->vehicle_number ?: 'Unassigned' }}</td>
                                <td>{{ $record->driver?->organization?->name ?: 'Not assigned' }}</td>
                                <td><span class="driver-status-badge {{ $record->attendance_type === 'ENTRY' ? 'is-active' : 'is-suspended' }}">{{ $record->attendance_type ?: 'UNKNOWN' }}</span></td>
                                <td>{{ $record->scan_method ?: 'Recorded' }}</td>
                                <td>{{ $record->terminal_id ?: 'N/A' }}</td>
                            </tr>
                        @else
                            <tr>
                                <td>{{ $record->created_at?->format('M j, Y H:i:s') }}</td>
                                <td class="fw-bold">{{ $record->activity }}</td>
                                <td>{{ $record->driver?->name ?: 'N/A' }}</td>
                                <td>{{ $record->driver?->organization?->name ?: 'N/A' }}</td>
                                <td>{{ $record->admin?->name ?: 'System' }}</td>
                                <td>{{ $record->description ?: 'N/A' }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="{{ $mode === 'attendance' ? 7 : 6 }}" class="text-center py-5 text-muted">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection