@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Attendance records</p>
            <h1 class="dashboard-page-title mb-2">Attendance</h1>
            <p class="dashboard-page-subtitle mb-0">{{ $organization->name }}</p>
        </div>
    </section>
    <section class="card dashboard-card border-0 shadow-sm">
        <form method="GET" action="{{ route('manager.attendance') }}" class="row g-3 p-3 border-bottom">
            <div class="col-md-3"><label class="form-label" for="filter-driver">Driver</label><select class="form-select" id="filter-driver" name="driver_id"><option value="">All drivers</option>@foreach ($drivers as $driver)<option value="{{ $driver->id }}" @selected(($filters['driver_id'] ?? '') == $driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="filter-type">Type</label><select class="form-select" id="filter-type" name="attendance_type"><option value="">All</option><option value="ENTRY" @selected(($filters['attendance_type'] ?? '') === 'ENTRY')>Entry</option><option value="EXIT" @selected(($filters['attendance_type'] ?? '') === 'EXIT')>Exit</option></select></div>
            <div class="col-md-2"><label class="form-label" for="filter-start">From</label><input class="form-control" type="date" id="filter-start" name="start_date" value="{{ $filters['start_date'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="filter-end">To</label><input class="form-control" type="date" id="filter-end" name="end_date" value="{{ $filters['end_date'] ?? '' }}"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary" type="submit">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-3">Driver</th><th>Type</th><th>Method</th><th>Recorded at</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr><td class="ps-3">{{ $record->driver?->name ?? 'Unknown driver' }}</td><td>{{ $record->attendance_type }}</td><td>{{ $record->scan_method }}</td><td>{{ optional($record->scanned_at ?: $record->created_at)->format('M j, Y H:i:s') }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No attendance records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $records->links() }}</div>
    </section>
@endsection
