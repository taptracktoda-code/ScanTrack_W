@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Manager dashboard</p>
            <h2 class="dashboard-page-title mb-2">WELCOME!</h2>
            <p class="dashboard-page-subtitle mb-0">Oraganization: {{ $organization->name }}.</p>
        </div>
    </section>

    <div class="row g-3 mb-4">
        @foreach ($stats as $stat)
            <div class="col-6 col-xl-3">
                <div class="card dashboard-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $stat['label'] }}</div>
                        <div class="fs-3 fw-bold">{{ number_format($stat['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <div class="fw-semibold">Please check the event details.</div>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card dashboard-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="section-title mb-1">Add an event</h3>
            <p class="section-subtitle mb-4">This event will appear on the schedules of drivers assigned to your TODA.</p>
            <form method="POST" action="{{ route('manager.schedules.store') }}" class="row g-3">
                @csrf
                <div class="col-12 col-lg-6">
                    <label for="event-title" class="form-label">Event title</label>
                    <input id="event-title" name="title" value="{{ old('title') }}" class="form-control" maxlength="150" required>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="event-date" class="form-label">Date</label>
                    <input id="event-date" type="date" name="event_date" value="{{ old('event_date') }}" class="form-control" required>
                </div>
                <div class="col-6 col-sm-3 col-lg-1">
                    <label for="event-start-time" class="form-label">Starts</label>
                    <input id="event-start-time" type="time" name="start_time" value="{{ old('start_time') }}" class="form-control" required>
                </div>
                <div class="col-6 col-sm-3 col-lg-2">
                    <label for="event-end-time" class="form-label">Ends</label>
                    <input id="event-end-time" type="time" name="end_time" value="{{ old('end_time') }}" class="form-control" required>
                </div>
                <div class="col-12 col-lg-5">
                    <label for="event-location" class="form-label">Location</label>
                    <input id="event-location" name="location" value="{{ old('location') }}" class="form-control" maxlength="255" required>
                </div>
                <div class="col-12 col-lg-7">
                    <label for="event-description" class="form-label">Description <span class="text-muted">(optional)</span></label>
                    <textarea id="event-description" name="description" class="form-control" rows="2" maxlength="5000">{{ old('description') }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-plus me-2"></i>Add to schedule</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card dashboard-card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h3 class="section-title mb-1">Scheduled events</h3>
                <p class="section-subtitle mb-0">Manage events visible to your TODA drivers.</p>
            </div>
            <div class="p-3 p-lg-4">
                @forelse ($events as $event)
                    <article class="border rounded-3 p-3 p-lg-4 mb-3">
                        <form id="event-update-{{ $event->id }}" method="POST" action="{{ route('manager.schedules.update', $event) }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-12 col-lg-5">
                                    <label for="event-title-{{ $event->id }}" class="form-label">Event title</label>
                                    <input id="event-title-{{ $event->id }}" name="title" value="{{ $event->title }}" class="form-control" maxlength="150" required>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label for="event-date-{{ $event->id }}" class="form-label">Date</label>
                                    <input id="event-date-{{ $event->id }}" type="date" name="event_date" value="{{ $event->event_date->format('Y-m-d') }}" class="form-control" required>
                                </div>
                                <div class="col-6 col-sm-3 col-lg-2">
                                    <label for="event-start-{{ $event->id }}" class="form-label">Starts</label>
                                    <input id="event-start-{{ $event->id }}" type="time" name="start_time" value="{{ substr($event->start_time, 0, 5) }}" class="form-control" required>
                                </div>
                                <div class="col-6 col-sm-3 col-lg-2">
                                    <label for="event-end-{{ $event->id }}" class="form-label">Ends</label>
                                    <input id="event-end-{{ $event->id }}" type="time" name="end_time" value="{{ substr($event->end_time, 0, 5) }}" class="form-control" required>
                                </div>
                                <div class="col-12 col-lg-5">
                                    <label for="event-location-{{ $event->id }}" class="form-label">Location</label>
                                    <input id="event-location-{{ $event->id }}" name="location" value="{{ $event->location }}" class="form-control" maxlength="255" required>
                                </div>
                                <div class="col-12 col-lg-7">
                                    <label for="event-description-{{ $event->id }}" class="form-label">Description</label>
                                    <textarea id="event-description-{{ $event->id }}" name="description" class="form-control" rows="2" maxlength="5000">{{ $event->description }}</textarea>
                                </div>
                                <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                                    <button form="event-update-{{ $event->id }}" class="btn btn-outline-primary"><i class="bi bi-check-lg me-1"></i>Save changes</button>
                        </form>
                                    <form method="POST" action="{{ route('manager.schedules.destroy', $event) }}" data-swal-delete data-swal-name="{{ $event->title }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete event</button>
                                    </form>
                                </div>
                            </div>
                    </article>
                @empty
                    <p class="text-center text-muted py-5 mb-0">No events have been scheduled yet.</p>
                @endforelse
                <div class="pt-2">{{ $events->links() }}</div>
            </div>
        </div>
    </section>
@endsection
