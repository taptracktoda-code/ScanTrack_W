@extends('layouts.app')

@section('content')
    <section class="dashboard-hero report-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="dashboard-page-title mb-1">Pending Logs</h2>
                <p class="dashboard-page-subtitle mb-0">Review recent QR entry and exit transactions.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="dashboard-mini-pill">
                    <i class="bi bi-hourglass-split me-2"></i>
                    {{ $count }} pending records
                </span>
            </div>
        </div>
    </section>

    <section class="driver-table-card mt-3">
        <div class="table-responsive">
            <table class="table driver-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Organization</th>
                        <th>Transaction</th>
                        <th>Scanned</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingLogs as $log)
                        <tr>
                            <td class="fw-bold">{{ $log->driver?->name ?: 'Unknown driver' }}</td>
                            <td>{{ $log->driver?->vehicle_number ?: 'Unassigned' }}</td>
                            <td>{{ $log->driver?->organization?->name ?: 'Not assigned' }}</td>
                            <td><span class="driver-status-badge {{ $log->attendance_type === 'ENTRY' ? 'is-active' : 'is-suspended' }}">{{ $log->attendance_type ?: 'UNKNOWN' }}</span></td>
                            <td>{{ optional($log->scanned_at ?: $log->created_at)->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No QR transactions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
