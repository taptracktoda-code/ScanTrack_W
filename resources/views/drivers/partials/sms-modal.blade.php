<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content driver-modal" action="{{ route('drivers.sendSms', $driver) }}" method="POST">
            @csrf

            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $modalId }}Label">Send SMS Notification</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="{{ $modalId }}-message">Message</label>
                    <textarea class="form-control" id="{{ $modalId }}-message" name="message" rows="4" required>{{ old('message') }}</textarea>
                </div>
                <div class="text-muted small">Sending to: {{ $driver->contact_number ?? 'No contact number' }}</div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn driver-primary-button">Send SMS</button>
            </div>
        </form>
    </div>
</div>
