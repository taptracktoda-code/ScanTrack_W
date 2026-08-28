@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Administration</p>
                <h2 class="dashboard-page-title mb-2">Settings</h2>
                <p class="dashboard-page-subtitle mb-0">Manage your profile and system preferences in one place.</p>
            </div>
        </div>
    </section>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card dashboard-card border-0 h-100 shadow-sm">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 class="section-title mb-1">Profile Settings</h3>
                                <p class="text-muted mb-0">Update your account details and contact information.</p>
                            </div>
                            <a href="{{ route('settings.profile') }}" class="btn btn-primary btn-sm shadow-sm">Open</a>
                        </div>
                        <div class="small text-muted">
                            <div class="mb-2"><strong>Name:</strong> {{ $user->name }}</div>
                            <div class="mb-2"><strong>Email:</strong> {{ $user->email }}</div>
                            <div><strong>Unit:</strong> {{ $user->unit }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card dashboard-card border-0 h-100 shadow-sm">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 class="section-title mb-1">TODA Organizations</h3>
                                <p class="text-muted mb-0">Add and review TODA organizations used by the system.</p>
                            </div>
                            <a href="{{ route('settings.toda_organizations') }}" class="btn btn-primary btn-sm shadow-sm">Open</a>
                        </div>
                        <p class="text-muted mb-0">Use this section to manage terminal organization records and coverage areas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
