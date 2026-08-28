@php
    $driverName = old('name', $driver->name ?? '');
    $licenseNumber = old('license_number', $driver->license_number ?? '');
    $contactNumber = old('contact_number', $driver->contact_number ?? '');
    $vehicleNumber = old('vehicle_number', $driver->vehicle_number ?? '');
    $driverStatus = old('status', $driver->status ?? 'Active');
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content driver-modal" action="{{ $action }}" method="POST">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $modalId }}Label">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="{{ $modalId }}-name">Driver name</label>
                        <input class="form-control" id="{{ $modalId }}-name" name="name" value="{{ $driverName }}" required>
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
                        <label class="form-label" for="{{ $modalId }}-vehicle">Vehicle num.</label>
                        <input class="form-control" id="{{ $modalId }}-vehicle" name="vehicle_number" value="{{ $vehicleNumber }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $modalId }}-status">Status</label>
                        <select class="form-select" id="{{ $modalId }}-status" name="status" required>
                            <option value="Active" @selected($driverStatus === 'Active')>Active</option>
                            <option value="Suspended" @selected($driverStatus === 'Suspended')>Suspended</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn driver-primary-button">Save Driver</button>
            </div>
        </form>
    </div>
</div>
