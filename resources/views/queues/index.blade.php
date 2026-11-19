@extends('layouts.app')

@push('styles')
    <link href="{{ asset('css/reports.css') }}" rel="stylesheet">
    <link href="{{ asset('css/queue-monitoring.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="reports-page queue-monitoring">
        <header class="reports-header">
            <div>
                <h1 class="reports-title">Queue Monitoring</h1>
                <p class="reports-description">Terminal attendance, live queue positions, and recorded trips. All times are in Asia/Manila.</p>
            </div>
            <a class="btn reports-primary-action" href="{{ route('reports') }}">Back to Reports</a>
        </header>

        <section class="reports-panel" aria-label="Queue filters and PDF export">
            <form method="GET" action="{{ route('queues.monitoring') }}" class="queue-filter-form">
                <div>
                    <label class="form-label" for="queue-terminal">Recorded terminal</label>
                    <select class="form-select" id="queue-terminal" name="terminal">
                        <option value="">All terminals</option>
                        @foreach ($terminals as $terminal)
                            <option value="{{ $terminal }}" @selected(($filters['terminal'] ?? null) === $terminal)>{{ $terminal }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="queue-organization">TODA association</label>
                    <select class="form-select" id="queue-organization" name="organization_id">
                        <option value="">All TODAs</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->id }}" @selected(($filters['organization_id'] ?? null) == $organization->id)>{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="queue-driver">Driver</label>
                    <select class="form-select" id="queue-driver" name="driver_id">
                        <option value="">All drivers</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected(($filters['driver_id'] ?? null) == $driver->id)>{{ $driver->name }} (#{{ $driver->id }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="queue-start">From date</label>
                    <input class="form-control" type="date" id="queue-start" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div>
                    <label class="form-label" for="queue-end">Through date</label>
                    <input class="form-control" type="date" id="queue-end" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div>
                    <label class="form-label" for="queue-status">Queue status</label>
                    <select class="form-select" id="queue-status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($errors->any())
                    <div class="queue-filter-actions text-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <div class="queue-filter-actions">
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                    <a class="btn btn-outline-secondary" href="{{ route('queues.monitoring') }}">Clear</a>
                </div>
            </form>

            <form method="GET" action="{{ route('reports.export') }}" class="queue-export-form">
                @foreach ($filters as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <div>
                    <label class="form-label" for="queue-report-type">PDF report</label>
                    <select class="form-select" id="queue-report-type" name="type">
                        @foreach ($reportTypes as $type => $title)
                            <option value="{{ $type }}" @selected($type === 'combined')>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-outline-primary" type="submit">Download PDF</button>
            </form>
        </section>

        <p id="queue-refresh-status" class="small text-muted mb-0" role="status">Refreshes automatically every 20 seconds while this tab is visible.</p>
        <div id="queue-live-records" data-feed="{{ route('queues.feed', $filters + ['page' => $records->currentPage()]) }}">
            @include('queues.records')
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/queue-monitoring.js') }}" defer></script>
@endpush
