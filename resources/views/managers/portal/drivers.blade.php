@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Driver management</p>
            <h1 class="dashboard-page-title mb-2">Drivers</h1>
            <p class="dashboard-page-subtitle mb-0">{{ $organization->name }}</p>
        </div>
    </section>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card dashboard-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="section-title mb-3">Register a driver</h2>
            <p class="section-subtitle">Driver accounts are activated immediately. Temporary login credentials are emailed to the driver.</p>
            <form method="POST" action="{{ route('manager.drivers.store') }}" class="row g-3">
                @csrf
                @foreach (['first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'email' => 'Email', 'contact_number' => 'Contact number', 'license_number' => 'License number', 'license_expiration_date' => 'License expiration', 'plate_number' => 'Plate number', 'vehicle_type' => 'Vehicle type'] as $field => $label)
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="driver-{{ $field }}">{{ $label }}</label>
                        <input class="form-control" id="driver-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" @if ($field === 'email') type="email" @elseif ($field === 'license_expiration_date') type="date" @endif @if ($field !== 'middle_name') required @endif>
                    </div>
                @endforeach
                <div class="col-12">
                    <label class="form-label" for="driver-address">Address</label>
                    <textarea class="form-control" id="driver-address" name="address" rows="2" required>{{ old('address') }}</textarea>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary" type="submit">Create driver account</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card dashboard-card border-0 shadow-sm">
        <div class="card-body p-0">
            <form method="GET" action="{{ route('manager.drivers') }}" class="p-3 border-bottom d-flex gap-2">
                <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search by name, license, or plate">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">Driver</th><th>License</th><th>Vehicle</th><th>Status</th><th>QR</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr>
                                <td class="ps-3">{{ $driver->name }}</td>
                                <td>{{ $driver->license_number }}</td>
                                <td>{{ $driver->vehicle_number }}</td>
                                <td>{{ $driver->status }}</td>
                                <td>{{ $driver->qrCode?->status ?? 'Not generated' }}</td>
                                <td class="text-end pe-3">
                                    @if (strcasecmp((string) $driver->status, 'Active') === 0)
                                        <form method="POST" action="{{ route('manager.drivers.qr.regenerate', $driver) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Regenerate QR</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No drivers are assigned to this organization yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $drivers->links() }}</div>
        </div>
    </section>
@endsection
