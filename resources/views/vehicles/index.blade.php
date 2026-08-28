@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
           
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#vehicleCreateModal">
                <i class="bi bi-plus-lg me-2"></i>
                Add Vehicle
            </button>
        </div>
    </section>

    <section class="card dashboard-card border-0">
        <div class="card-body p-4">
            <h3 class="section-title mb-2">Vehicle management</h3>
            <p class="section-subtitle mb-0">Registered vehicles appear here and can be assigned to drivers.</p>

            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Plate Number</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles ?? [] as $vehicle)
                            <tr>
                                <td>{{ $vehicle->name }}</td>
                                <td>{{ $vehicle->plate_number }}</td>
                                <td>{{ $vehicle->status }}</td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button
                                            class="btn btn-outline-secondary btn-sm"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#vehicleEditModal-{{ $vehicle->id }}"
                                            aria-label="Edit {{ $vehicle->name }}"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>

                                        <form action="{{ route('vehicles.destroy', $vehicle) }}" method="POST" data-swal-delete data-swal-name="{{ $vehicle->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Delete {{ $vehicle->name }}" title="Delete">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="vehicleEditModal-{{ $vehicle->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('vehicles.update', $vehicle) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Vehicle</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Vehicle Name</label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $vehicle->name) }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Plate Number</label>
                                                    <input type="text" name="plate_number" class="form-control" value="{{ old('plate_number', $vehicle->plate_number) }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="Active" {{ old('status', $vehicle->status) === 'Active' ? 'selected' : '' }}>Active</option>
                                                        <option value="Inactive" {{ old('status', $vehicle->status) === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No vehicles have been registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="vehicleCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('vehicles.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Vehicle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Vehicle Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Plate Number</label>
                            <input type="text" name="plate_number" class="form-control @error('plate_number') is-invalid @enderror" value="{{ old('plate_number') }}" required>
                            @error('plate_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="Active" {{ old('status') === 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Inactive" {{ old('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
