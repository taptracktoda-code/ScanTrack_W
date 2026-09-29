@extends('layouts.app')

@section('content')
    <section class="dashboard-hero report-hero mb-4">
        <div>
            <h1 class="dashboard-page-title mb-1">{{ $title }}</h1>
            <p class="dashboard-page-subtitle mb-0">{{ $description }}</p>
        </div>
    </section>

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