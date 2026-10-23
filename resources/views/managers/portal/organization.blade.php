@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Organization details</p>
            <h1 class="dashboard-page-title mb-2">{{ $organization->name }}</h1>
            <p class="dashboard-page-subtitle mb-0">Information for your assigned TODA.</p>
        </div>
    </section>
    <section class="card dashboard-card border-0 shadow-sm"><div class="card-body p-4">
        <dl class="row mb-0">
            <dt class="col-sm-3">Organization</dt><dd class="col-sm-9">{{ $organization->name }}</dd>
            <dt class="col-sm-3">Barangay / travel area</dt><dd class="col-sm-9">{{ $organization->barangay_travel_area ?: 'Not specified' }}</dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ $organization->status }}</dd>
        </dl>
    </div></section>
@endsection
