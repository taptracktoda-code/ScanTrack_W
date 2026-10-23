@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Operations monitoring</p>
            <h2 class="dashboard-page-title mb-2">Queue Monitoring</h2>
            <p class="dashboard-page-subtitle mb-0">Live organization queue state. Queue controls are available to Managers in the mobile API.</p>
        </div>
    </section>

    <section class="card dashboard-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-uppercase small text-muted">
                    <tr><th class="ps-4">Organization</th><th>Current Driver</th><th>Queue Number</th><th>Drivers Waiting</th><th>Queue Status</th><th>Last Served</th></tr>
                </thead>
                <tbody>
                    @forelse ($organizations as $queue)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $queue['organization']->name }}</td>
                            <td>{{ $queue['current']?->driver?->name ?? 'No driver being served' }}</td>
                            <td>{{ $queue['current']?->queue_number ?? '—' }}</td>
                            <td><span class="badge text-bg-primary">{{ $queue['waiting'] }}</span></td>
                            <td><span class="badge {{ $queue['state'] === 'Serving' ? 'text-bg-success' : ($queue['state'] === 'Waiting' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ $queue['state'] }}</span></td>
                            <td>
                                {{ $queue['last_served']?->driver?->name ?? '—' }}
                                @if ($queue['last_served']?->completed_at)
                                    <div class="small text-muted">{{ $queue['last_served']->completed_at->diffForHumans() }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No organizations are configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card dashboard-card border-0 shadow-sm">
        <div class="card-body p-4">
            <h3 class="section-title mb-3">Recent Queue Activity</h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-uppercase small text-muted"><tr><th>Time</th><th>Activity</th><th>Organization</th><th>Driver</th><th>Manager</th><th>Details</th></tr></thead>
                    <tbody>
                        @forelse ($activities as $activity)
                            <tr>
                                <td>{{ $activity->created_at?->format('M j, Y H:i') }}</td>
                                <td class="fw-semibold">{{ $activity->activity }}</td>
                                <td>{{ $activity->driver?->organization?->name ?? '—' }}</td>
                                <td>{{ $activity->driver?->name ?? '—' }}</td>
                                <td>{{ $activity->admin?->name ?? '—' }}</td>
                                <td>{{ $activity->description ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No queue activity has been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection