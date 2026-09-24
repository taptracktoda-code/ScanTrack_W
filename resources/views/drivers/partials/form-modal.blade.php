@php
    $isCreate = $method === 'POST';
    $driverName = old('name', $driver->name ?? '');
    $licenseNumber = old('license_number', $driver->license_number ?? '');
    $contactNumber = old('contact_number', $driver->contact_number ?? '');
    $vehicleNumber = old('vehicle_number', $driver->vehicle_number ?? '');
    $driverStatus = old('status', $driver->status ?? 'Active');
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $isCreate ? '' : 'modal-lg driver-edit-dialog' }}">
        <form class="modal-content driver-modal {{ $isCreate ? '' : 'driver-edit-modal' }}" action="{{ $action }}" method="POST">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="modal-header">
                <div>
                    <div class="driver-modal-kicker">{{ $isCreate ? 'New account' : 'Driver profile' }}</div>
                    <h2 class="modal-title fs-5" id="{{ $modalId }}Label">{{ $title }}</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @if (!$isCreate)
                    <div class="driver-edit-layout">
                        <aside class="driver-edit-aside">
                            <div class="driver-edit-aside-kicker">Driver profile</div>
                            <span class="driver-edit-avatar">{{ strtoupper(collect(explode(' ', $driverName))->map(fn ($part) => substr($part, 0, 1))->take(2)->implode('')) }}</span>
                            <div class="driver-edit-aside-name">{{ $driverName }}</div>
                            <span class="driver-status-badge {{ $driverStatus === 'Active' ? 'is-active' : 'is-suspended' }}">{{ $driverStatus }}</span>
                            <div class="driver-edit-aside-details">
                                <div><span>Vehicle</span><strong>{{ $vehicleNumber ?: 'Not assigned' }}</strong></div>
                                <div><span>License</span><strong>{{ $licenseNumber ?: 'Not set' }}</strong></div>
                            </div>
                        </aside>
                        <div class="driver-edit-fields">
                            <div class="driver-form-section-label">Driver identity</div>
                            <div class="row g-3 driver-form-grid">
                                <div class="col-12">
                                    <label class="form-label" for="{{ $modalId }}-name">Full name</label>
                                    <input class="form-control form-control-lg" id="{{ $modalId }}-name" name="name" value="{{ $driverName }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $modalId }}-license">License No.</label>
                                    <input class="form-control" id="{{ $modalId }}-license" name="license_number" value="{{ $licenseNumber }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $modalId }}-contact">Contact</label>
                                    <input class="form-control" id="{{ $modalId }}-contact" name="contact_number" value="{{ $contactNumber }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $modalId }}-vehicle">Vehicle number</label>
                                    <input class="form-control" id="{{ $modalId }}-vehicle" name="vehicle_number" value="{{ $vehicleNumber }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $modalId }}-status">Account status</label>
                                    <select class="form-select" id="{{ $modalId }}-status" name="status" required>
                                        <option value="Active" @selected($driverStatus === 'Active')>Active</option>
                                        <option value="Suspended" @selected($driverStatus === 'Suspended')>Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="row g-3 driver-form-grid">
                    @if ($isCreate)
                        <div class="col-md-4">
                            <label class="form-label" for="{{ $modalId }}-first-name">First name</label>
                            <input class="form-control" id="{{ $modalId }}-first-name" name="first_name" value="{{ old('first_name') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="{{ $modalId }}-middle-name">Middle name</label>
                            <input class="form-control" id="{{ $modalId }}-middle-name" name="middle_name" value="{{ old('middle_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="{{ $modalId }}-last-name">Last name</label>
                            <input class="form-control" id="{{ $modalId }}-last-name" name="last_name" value="{{ old('last_name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}-email">Gmail</label>
                            <input class="form-control" type="email" id="{{ $modalId }}-email" name="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}-address">Address</label>
                            <input class="form-control" id="{{ $modalId }}-address" name="address" value="{{ old('address') }}" required>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $modalId }}-license">License No.</label>
                        <input class="form-control" id="{{ $modalId }}-license" name="license_number" value="{{ $licenseNumber }}" required>
                    </div>
                    @if ($isCreate)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}-license-expiration">License expiration</label>
                            <input class="form-control" type="date" id="{{ $modalId }}-license-expiration" name="license_expiration_date" value="{{ old('license_expiration_date') }}" required>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $modalId }}-contact">Contact</label>
                        <input class="form-control" id="{{ $modalId }}-contact" name="contact_number" value="{{ $contactNumber }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $modalId }}-vehicle">Vehicle num.</label>
                        <input class="form-control" id="{{ $modalId }}-vehicle" name="{{ $isCreate ? 'plate_number' : 'vehicle_number' }}" value="{{ $isCreate ? old('plate_number') : $vehicleNumber }}" {{ $isCreate ? 'required' : '' }}>
                    </div>
                    @if ($isCreate)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}-vehicle-type">Vehicle type</label>
                            <input class="form-control" id="{{ $modalId }}-vehicle-type" name="vehicle_type" value="{{ old('vehicle_type') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}-organization">Organization / TODA</label>
                            <select class="form-select" id="{{ $modalId }}-organization" name="organization_id" required>
                                <option value="">Select organization</option>
                                @foreach ($organizations as $organization)
                                    <option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>{{ $organization->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $modalId }}-status">Status</label>
                        <select class="form-select" id="{{ $modalId }}-status" name="status" required>
                            <option value="Active" @selected($driverStatus === 'Active')>Active</option>
                            <option value="Suspended" @selected($driverStatus === 'Suspended')>Suspended</option>
                        </select>
                    </div>
                </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light driver-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn driver-primary-button">{{ $isCreate ? 'Save Driver' : 'Update Driver' }}</button>
            </div>
        </form>
    </div>
</div>
