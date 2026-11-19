@extends('layouts.app')

@push('styles')
    <link href="{{ asset('css/admin-drivers.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php
        $statCards = [
            ['label' => 'Active drivers', 'value' => $stats['active'], 'icon' => 'bi-person-check', 'tone' => 'green'],
            ['label' => 'Active with vehicle', 'value' => $stats['in_service'], 'icon' => 'bi-truck-front', 'tone' => 'blue'],
            ['label' => 'Updated today', 'value' => $stats['today'], 'icon' => 'bi-calendar-check', 'tone' => 'violet'],
            ['label' => 'Suspended', 'value' => $stats['suspended'], 'icon' => 'bi-person-slash', 'tone' => 'red'],
        ];
    @endphp

    <div class="admin-drivers">
        <header class="admin-drivers-header">
            <h1>Drivers</h1>
            <p>Manage driver accounts, vehicles, and assignments.</p>
        </header>

        @if (session('status'))
            <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-0" role="alert">
                <strong>Please check the driver details and try again.</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-drivers-metrics" aria-label="Driver totals">
            @foreach ($statCards as $stat)
                <article class="admin-drivers-metric">
                    <span class="admin-drivers-metric-icon is-{{ $stat['tone'] }}"><i class="bi {{ $stat['icon'] }}" aria-hidden="true"></i></span>
                    <div>
                        <div class="admin-drivers-metric-label">{{ $stat['label'] }}</div>
                        <strong>{{ number_format($stat['value']) }}</strong>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="admin-driver-directory" aria-labelledby="driver-directory-heading">
            <div class="admin-driver-directory-heading">
                <div>
                    <h2 id="driver-directory-heading">Driver directory</h2>
                    <p>
                        {{ number_format($drivers->count()) }}
                        @if ($search !== '')
                            {{ \Illuminate\Support\Str::plural('result', $drivers->count()) }} for <strong>{{ $search }}</strong>
                        @else
                            {{ \Illuminate\Support\Str::plural('driver', $drivers->count()) }}
                        @endif
                    </p>
                </div>
                <form class="admin-driver-search" action="{{ route('drivers') }}" method="GET" role="search" aria-label="Driver directory">
                    <div class="admin-driver-search-field">
                        <label for="driver-directory-search" class="visually-hidden">Search by name, driver ID, plate, or TODA</label>
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="driver-directory-search" class="form-control" name="search" type="search" value="{{ $search }}"
                            placeholder="Name, driver ID, plate, or TODA">
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                    @if ($search !== '')
                        <a class="btn btn-link" href="{{ route('drivers') }}">Clear filter</a>
                    @endif
                </form>
            </div>

            @if ($drivers->isEmpty())
                <div class="admin-drivers-empty">
                    <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                    <h3>{{ $search !== '' ? 'No matching drivers' : 'No drivers yet' }}</h3>
                    @if ($search !== '')
                        <p>Search for a different name, driver ID, plate, or TODA.</p>
                    @endif
                </div>
            @else
                <table class="table mb-0 admin-driver-table" role="table" aria-labelledby="driver-directory-heading">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Driver</th>
                            <th scope="col" role="columnheader">Contact and license</th>
                            <th scope="col" role="columnheader">Assignments</th>
                            <th scope="col" role="columnheader">Account</th>
                            <th scope="col" role="columnheader">Duty</th>
                            <th scope="col" role="columnheader" class="admin-driver-actions-heading">Actions</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @foreach ($drivers as $driver)
                            @php
                                $displayName = $driver->name ?: 'Unnamed Driver';
                                $initials = collect(preg_split('/\s+/u', trim($displayName), -1, PREG_SPLIT_NO_EMPTY))
                                    ->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                                $accountId = 'DRIVER-' . $driver->id;
                                $driverAccount = $driverAccounts->get($accountId);
                                $shiftStatus = $driverAccount?->shift_status ?: 'Available';
                                $accountTone = match ($driver->status) {
                                    'Active' => 'green',
                                    'Pending' => 'amber',
                                    'Suspended', 'Rejected' => 'red',
                                    default => 'neutral',
                                };
                                $dutyTone = match ($shiftStatus) {
                                    'On Break' => 'amber',
                                    'Off Duty' => 'neutral',
                                    default => 'green',
                                };
                            @endphp
                            <tr role="row">
                                <th scope="row" role="rowheader" class="admin-driver-identity">
                                    <div class="admin-driver-person">
                                        <span class="admin-driver-avatar" aria-hidden="true">{{ $initials }}</span>
                                        <div>
                                            <strong>{{ $displayName }}</strong>
                                            <span class="admin-driver-detail">{{ $accountId }}</span>
                                        </div>
                                    </div>
                                </th>
                                <td role="cell">
                                    <span class="admin-driver-cell-label" aria-hidden="true">Contact and license</span>
                                    <div>{{ $driver->contact_number ?: 'Contact not set' }}</div>
                                    <span class="admin-driver-detail">License · {{ $driver->license_number ?: 'Not set' }}</span>
                                </td>
                                <td role="cell">
                                    <span class="admin-driver-cell-label" aria-hidden="true">Assignments</span>
                                    <div>
                                        @if ($driver->vehicle_number)
                                            <span class="admin-driver-plate">{{ $driver->vehicle_number }}</span>
                                        @else
                                            <span class="admin-driver-detail">No vehicle assigned</span>
                                        @endif
                                    </div>
                                    <div class="admin-driver-organization">{{ $driver->organization?->name ?: 'No TODA assigned' }}</div>
                                    @if ($driver->assigned_terminal)
                                        <span class="admin-driver-detail">Terminal · {{ $driver->assigned_terminal }}</span>
                                    @endif
                                </td>
                                <td role="cell">
                                    <span class="admin-driver-cell-label" aria-hidden="true">Account</span>
                                    <span class="admin-driver-badge is-{{ $accountTone }}">{{ $driver->status }}</span>
                                </td>
                                <td role="cell">
                                    <span class="admin-driver-cell-label" aria-hidden="true">Duty</span>
                                    <span class="admin-driver-badge is-{{ $dutyTone }}">{{ $shiftStatus }}</span>
                                </td>
                                <td role="cell" class="admin-driver-action-cell">
                                    <div class="admin-driver-actions">
                                        <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#driverEditModal-{{ $driver->id }}"
                                            aria-label="Edit {{ $displayName }}" title="Edit driver">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                        @if ($driver->qrCode)
                                            <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#driverQrModal-{{ $driver->id }}"
                                                aria-label="View QR code for {{ $displayName }}" title="View QR code">
                                                <i class="bi bi-qr-code" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                        @if ($driverAccount)
                                            <form action="{{ route('drivers.resendCredentials', $driver) }}" method="POST">
                                                @csrf
                                                <button class="btn" type="submit" aria-label="Resend credentials to {{ $displayName }}" title="Resend temporary password">
                                                    <i class="bi bi-envelope-arrow-up" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('drivers.destroy', $driver) }}" method="POST" data-swal-delete data-swal-name="{{ $displayName }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn admin-driver-delete" type="submit" aria-label="Delete {{ $displayName }}" title="Delete driver">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    @foreach ($drivers as $driver)
        @include('drivers.partials.form-modal', [
            'modalId' => 'driverEditModal-' . $driver->id,
            'title' => 'Edit Driver',
            'action' => route('drivers.update', $driver),
            'method' => 'PUT',
            'driver' => $driver,
        ])

        @if ($driver->qrCode)
            @include('drivers.partials.qr-modal', ['driver' => $driver, 'displayName' => $driver->name ?: 'Unnamed Driver'])
        @endif
    @endforeach
@endsection
