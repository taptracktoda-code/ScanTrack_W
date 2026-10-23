@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Fleet management</p>
            <h1 class="dashboard-page-title mb-2">Vehicles</h1>
            <p class="dashboard-page-subtitle mb-0">{{ $organization->name }}</p>
        </div>
    </section>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <section class="card dashboard-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="section-title mb-3">Add vehicle</h2>
            <form method="POST" action="{{ route('manager.vehicles.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3"><label class="form-label" for="vehicle-name">Vehicle name</label><input class="form-control" id="vehicle-name" name="name" required></div>
                <div class="col-md-3"><label class="form-label" for="vehicle-plate">Plate number</label><input class="form-control" id="vehicle-plate" name="plate_number" required></div>
                <div class="col-md-3"><label class="form-label" for="vehicle-type">Vehicle type</label><input class="form-control" id="vehicle-type" name="vehicle_type"></div>
                <div class="col-md-2"><label class="form-label" for="vehicle-status">Status</label><select class="form-select" id="vehicle-status" name="status"><option>Active</option><option>Inactive</option></select></div>
                <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary" type="submit">Add</button></div>
            </form>
        </div>
    </section>

    <section class="card dashboard-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-3">Vehicle</th><th>Plate</th><th>Type</th><th>Driver</th><th>Status</th><th>Update</th><th></th></tr></thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td class="ps-3">{{ $vehicle->name }}</td><td>{{ $vehicle->plate_number }}</td><td>{{ $vehicle->vehicle_type ?: '—' }}</td><td>{{ $vehicle->driver?->name ?: 'Unassigned' }}</td><td>{{ $vehicle->status }}</td>
                            <td>
                                <form id="vehicle-update-{{ $vehicle->id }}" method="POST" action="{{ route('manager.vehicles.update', $vehicle) }}">
                                    @csrf @method('PUT')
                                    <input class="form-control form-control-sm mb-1" name="name" value="{{ $vehicle->name }}" aria-label="Vehicle name" required>
                                    <input class="form-control form-control-sm mb-1" name="plate_number" value="{{ $vehicle->plate_number }}" aria-label="Plate number" required>
                                    <input class="form-control form-control-sm mb-1" name="vehicle_type" value="{{ $vehicle->vehicle_type }}" aria-label="Vehicle type">
                                    <select class="form-select form-select-sm" name="status" aria-label="Vehicle status"><option @selected($vehicle->status === 'Active')>Active</option><option @selected($vehicle->status === 'Inactive')>Inactive</option></select>
                                </form>
                            </td>
                            <td><button class="btn btn-sm btn-outline-primary" form="vehicle-update-{{ $vehicle->id }}">Save</button>
                                <form method="POST" action="{{ route('manager.vehicles.destroy', $vehicle) }}" data-swal-delete data-swal-name="{{ $vehicle->name }}" class="mt-1">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No vehicles are assigned to this organization.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $vehicles->links() }}</div>
    </section>
@endsection
