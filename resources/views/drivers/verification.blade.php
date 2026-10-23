@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Driver administration</p>
            <h2 class="dashboard-page-title mb-2">Driver Verification</h2>
            <p class="dashboard-page-subtitle mb-0">Review driver applications and manager submissions before activating driver access.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card dashboard-card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-uppercase small text-muted">
                    <tr><th class="ps-4">Driver</th><th>Contact / License</th><th>Organization</th><th>Vehicle</th><th>Registered</th><th>Status</th><th class="text-end pe-4">Decision</th></tr>
                </thead>
                <tbody>
                    @forelse ($drivers as $driver)
                        @php($account = $accounts->get('DRIVER-' . strtoupper((string) $driver->id)))
                        <tr>
                            <td class="ps-4"><strong>{{ $driver->name }}</strong><div class="small text-muted">{{ $account?->email }}</div><div class="small text-muted">{{ $driver->address }}</div>@if ($account?->unit)<div class="small text-muted">Unit: {{ $account->unit }}</div>@endif</td>
                            <td>{{ $driver->contact_number }}<div class="small text-muted">{{ $driver->license_number }} · expires {{ $driver->license_expiration_date?->format('M j, Y') ?? 'not set' }}</div></td>
                            <td>{{ $driver->organization?->name ?? 'Unassigned' }}</td>
                            <td>{{ $driver->vehicle_number }}<div class="small text-muted">{{ $driver->vehicle?->vehicle_type }}</div></td>
                            <td>{{ $driver->created_at?->format('M j, Y') }}</td>
                            <td><span class="badge text-bg-warning">Pending</span></td>
                            <td class="text-end pe-4">
                                @if (! $driver->organization_id || ! $driver->vehicle_number)
                                    <form method="POST" action="{{ route('drivers.assign', $driver) }}" class="d-grid gap-2 mb-2">
                                        @csrf
                                        <select name="organization_id" class="form-select form-select-sm" aria-label="Assign organization" required>
                                            <option value="">Assign organization</option>
                                            @foreach ($organizations as $organization)
                                                <option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>{{ $organization->name }}</option>
                                            @endforeach
                                        </select>
                                        <input name="plate_number" class="form-control form-control-sm" value="{{ old('plate_number') }}" maxlength="80" placeholder="Vehicle plate number" required>
                                        <input name="vehicle_type" class="form-control form-control-sm" value="{{ old('vehicle_type') }}" maxlength="80" placeholder="Vehicle type" required>
                                        <button class="btn btn-sm btn-primary">Assign and continue</button>
                                    </form>
                                @else
                                    <div class="d-flex justify-content-end gap-2">
                                        <form method="POST" action="{{ route('drivers.approve', $driver) }}">@csrf<button class="btn btn-sm btn-success" title="Approve and email temporary credentials" aria-label="Approve driver"><i class="bi bi-check-lg"></i></button></form>
                                    </div>
                                @endif
                                <button class="btn btn-sm btn-outline-danger mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#reject-{{ $driver->id }}" aria-expanded="false" aria-controls="reject-{{ $driver->id }}" title="Reject driver" aria-label="Reject driver"><i class="bi bi-x-lg"></i></button>
                                <form id="reject-{{ $driver->id }}" class="collapse mt-2" method="POST" action="{{ route('drivers.reject', $driver) }}">
                                    @csrf
                                    <label class="visually-hidden" for="reason-{{ $driver->id }}">Rejection reason</label>
                                    <textarea id="reason-{{ $driver->id }}" name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" placeholder="Reason for rejection" required></textarea>
                                    <button class="btn btn-sm btn-danger w-100">Confirm rejection</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">There are no pending driver registrations.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $drivers->links() }}</div>
    </section>
@endsection