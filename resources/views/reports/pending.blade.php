@extends('layouts.app')

@section('content')
    <section class="dashboard-hero report-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="dashboard-page-title mb-1">Pending Logs</h2>
                <p class="dashboard-page-subtitle mb-0">Review recent pending driver logs.</p>
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
                        <th>Log ID</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingLogs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ optional($log->created_at)->format('M j, Y H:i') }}</td>
                            <td>{{ optional($log->updated_at)->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No pending logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
