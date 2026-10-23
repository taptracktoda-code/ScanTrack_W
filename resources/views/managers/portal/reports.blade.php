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
