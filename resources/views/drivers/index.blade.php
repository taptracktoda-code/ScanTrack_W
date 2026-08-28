@extends('layouts.app')

@section('content')
    @php
        $statCards = [
            ['label' => 'Active Driver', 'value' => $stats['active'], 'class' => 'driver-stat-coral'],
            ['label' => 'Vehicle in service', 'value' => $stats['in_service'], 'class' => 'driver-stat-mint'],
            ['label' => 'Active Today', 'value' => $stats['today'], 'class' => 'driver-stat-yellow'],
            ['label' => 'Suspended', 'value' => $stats['suspended'], 'class' => 'driver-stat-rose'],
        ];
    @endphp

    <section class="dashboard-hero driver-hero mb-4">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            
        </div>
    </section>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            Please check the driver details and try again.
        </div>
    @endif

    <section class="row g-3 g-xl-4 mb-3">
        @foreach ($statCards as $stat)
            <div class="col-sm-6 col-xl-3">
                <div class="driver-stat-card {{ $stat['class'] }}">
                    <div class="driver-stat-label">{{ $stat['label'] }}</div>
                    <div class="driver-stat-value">{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="driver-table-card">
        <div class="table-responsive">
            <table class="table driver-table align-middle mb-0">
                <thead>
                    <tr>
                        <th></th>
                        <th>Driver name</th>
                        <th>License No.</th>
                        <th>Contact</th>
                        <th>Vehicle num.</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drivers as $driver)
                        @php
                            $displayName = $driver->name ?: 'Unnamed Driver';
                            $initials = strtoupper(collect(explode(' ', $displayName))->map(fn ($part) => substr($part, 0, 1))->take(2)->implode(''));
                        @endphp
                        <tr>
                            <td>
                                <span class="driver-avatar-sm">{{ $initials }}</span>
                            </td>
                            <td class="fw-bold">{{ $displayName }}</td>
                            <td>{{ $driver->license_number ?: 'Not set' }}</td>
                            <td>{{ $driver->contact_number ?: 'Not set' }}</td>
                            <td class="fw-bold">{{ $driver->vehicle_number ?: 'Unassigned' }}</td>
                            <td>
                                <span class="driver-status-badge {{ $driver->status === 'Active' ? 'is-active' : 'is-suspended' }}">
                                    {{ $driver->status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <button
                                        class="btn driver-icon-btn"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#driverSmsModal-{{ $driver->id }}"
                                        aria-label="Send SMS to {{ $displayName }}"
                                        title="Send SMS"
                                    >
                                        <i class="bi bi-chat-dots-fill"></i>
                                    </button>
                                    <button
                                        class="btn driver-icon-btn"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#driverEditModal-{{ $driver->id }}"
                                        aria-label="Edit {{ $displayName }}"
                                    >
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form action="{{ route('drivers.destroy', $driver) }}" method="POST" data-swal-delete data-swal-name="{{ $displayName }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn driver-icon-btn" type="submit" aria-label="Delete {{ $displayName }}">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        @include('drivers.partials.form-modal', [
                            'modalId' => 'driverEditModal-' . $driver->id,
                            'title' => 'Edit Driver',
                            'action' => route('drivers.update', $driver),
                            'method' => 'PUT',
                            'driver' => $driver,
                        ])

                        @include('drivers.partials.sms-modal', [
                            'modalId' => 'driverSmsModal-' . $driver->id,
                            'driver' => $driver,
                        ])
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No drivers yet. Add your first driver to start tracking records.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('drivers.partials.form-modal', [
        'modalId' => 'driverCreateModal',
        'title' => 'Add New Driver',
        'action' => route('drivers.store'),
        'method' => 'POST',
        'driver' => null,
    ])
@endsection
