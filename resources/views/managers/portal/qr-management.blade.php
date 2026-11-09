@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Identity management</p>
            <h1 class="dashboard-page-title mb-2">QR Management</h1>
            <p class="dashboard-page-subtitle mb-0">QR credentials for {{ $organization->name }} drivers.</p>
        </div>
    </section>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card dashboard-card border-0 shadow-sm">
        <form method="GET" action="{{ route('manager.qr.management') }}" class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label" for="qr-status">QR status</label><select id="qr-status" class="form-select" name="status"><option value="">All statuses</option>@foreach (['Active', 'Inactive'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
            <button class="btn btn-outline-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('manager.qr.management') }}">Clear</a>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th class="ps-3">Driver</th><th>Plate</th><th>QR Identifier</th><th>Status</th><th>Generated</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($qrCodes as $qrCode)
                        <tr>
                            <td class="ps-3">{{ $qrCode->driver?->name ?? 'Unassigned' }}</td>
                            <td>{{ $qrCode->driver?->vehicle_number ?? '—' }}</td>
                            <td><code>{{ $qrCode->qr_token }}</code></td>
                            <td>{{ $qrCode->status }}</td>
                            <td>{{ optional($qrCode->generated_at ?: $qrCode->created_at)->format('M j, Y H:i') }}</td>
                            <td>
                                @if ($qrCode->driver)
                                    <form method="POST" action="{{ route('manager.drivers.qr.regenerate', $qrCode->driver) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Regenerate</button></form>
                                    <form method="POST" action="{{ route('manager.drivers.qr.status', ['driver' => $qrCode->driver, 'status' => $qrCode->status === 'Active' ? 'deactivate' : 'activate']) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $qrCode->status === 'Active' ? 'Deactivate' : 'Activate' }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ request()->filled('status') ? 'No QR records match this status.' : 'No QR records exist for this organization.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $qrCodes->links() }}</div>
    </section>
@endsection
