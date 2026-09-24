@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Identity management</p>
                <h1 class="h3 mb-1">QR Management</h1>
                <p class="mb-0 text-muted">Manage the QR identifiers assigned to every driver.</p>
            </div>
            <span class="dashboard-mini-pill"><i class="bi bi-shield-check me-2"></i>Server-managed identifiers</span>
        </div>
    </section>

    <section class="driver-table-card">
        <form class="p-3 border-bottom" method="GET" action="{{ route('qr.management') }}">
            <div class="row g-2 align-items-center">
                <div class="col-lg-8">
                    <label class="visually-hidden" for="qr-search">Search QR records</label>
                    <input class="form-control" id="qr-search" name="search" value="{{ $search }}" placeholder="Search driver, plate, organization, or QR identifier">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="visually-hidden" for="qr-status">Filter QR status</label>
                    <select class="form-select" id="qr-status" name="status">
                        <option value="">All statuses</option>
                        <option value="Active" @selected($status === 'Active')>Active</option>
                        <option value="Inactive" @selected($status === 'Inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-1 d-grid">
                    <button class="btn btn-primary" type="submit" aria-label="Search QR codes"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table driver-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>QR Identifier</th>
                        <th>Plate</th>
                        <th>Organization</th>
                        <th>Status</th>
                        <th>Generated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($qrCodes as $qrCode)
                        <tr>
                            <td class="fw-bold">{{ $qrCode->driver?->name ?: 'Unassigned' }}</td>
                            <td><code>{{ $qrCode->qr_token }}</code></td>
                            <td>{{ $qrCode->driver?->vehicle_number ?: 'Unassigned' }}</td>
                            <td>{{ $qrCode->driver?->organization?->name ?: 'Not assigned' }}</td>
                            <td><span class="driver-status-badge {{ $qrCode->status === 'Active' ? 'is-active' : 'is-suspended' }}">{{ $qrCode->status }}</span></td>
                            <td>{{ optional($qrCode->generated_at ?: $qrCode->created_at)->format('M j, Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($qrCode->driver)
                                    <a class="btn driver-icon-btn" href="{{ route('drivers.qr', $qrCode->driver) }}" target="_blank" title="View QR" aria-label="View QR"><i class="bi bi-eye"></i></a>
                                    <a class="btn driver-icon-btn" href="{{ route('drivers.qr', ['driver' => $qrCode->driver, 'download' => 1]) }}" title="Download QR" aria-label="Download QR"><i class="bi bi-download"></i></a>
                                    <button class="btn driver-icon-btn" type="button" title="Print QR" aria-label="Print QR" onclick="window.open('{{ route('drivers.qr', $qrCode->driver) }}', '_blank')?.print()"><i class="bi bi-printer"></i></button>
                                    <form class="d-inline" method="POST" action="{{ route('drivers.qr.regenerate', $qrCode->driver) }}">
                                        @csrf
                                        <button class="btn driver-icon-btn" type="submit" title="Regenerate QR" aria-label="Regenerate QR"><i class="bi bi-arrow-repeat"></i></button>
                                    </form>
                                    <form class="d-inline" method="POST" action="{{ route($qrCode->status === 'Active' ? 'drivers.qr.deactivate' : 'drivers.qr.activate', $qrCode->driver) }}">
                                        @csrf
                                        <button class="btn driver-icon-btn" type="submit" title="{{ $qrCode->status === 'Active' ? 'Deactivate QR' : 'Activate QR' }}" aria-label="{{ $qrCode->status === 'Active' ? 'Deactivate QR' : 'Activate QR' }}"><i class="bi {{ $qrCode->status === 'Active' ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No QR records match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection