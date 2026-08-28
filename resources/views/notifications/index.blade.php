@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
           
        </div>
    </section>

    <section class="card dashboard-card border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="section-title mb-1">Recent notifications</h3>
                    <p class="section-subtitle mb-0">This page is dedicated to your notification stream.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm">Mark all as read</button>
            </div>

            <div class="list-group list-group-flush">
                <div class="list-group-item px-0 py-4 border-top-0 border-bottom">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <h4 class="mb-1">No new notifications</h4>
                            <p class="mb-0 text-muted">You have no unread notifications at the moment.</p>
                        </div>
                        <span class="badge bg-success align-self-start">Healthy</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
