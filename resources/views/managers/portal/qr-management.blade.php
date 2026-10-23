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
    <section class="card dashboard-card border-0 shadow-sm">
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
                        <tr><td colspan="6" class="text-center text-muted py-4">No QR records exist for this organization.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $qrCodes->links() }}</div>
    </section>
@endsection
