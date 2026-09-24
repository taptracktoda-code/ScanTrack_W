<div class="modal fade" id="driverQrModal-{{ $driver->id }}" tabindex="-1" aria-labelledby="driverQrModalLabel-{{ $driver->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content driver-modal text-center">
            <div class="modal-header">
                <div>
                    <div class="driver-modal-kicker">Active QR code</div>
                    <h2 class="modal-title fs-5" id="driverQrModalLabel-{{ $driver->id }}">{{ $displayName }}</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img class="img-fluid mx-auto d-block" src="{{ route('drivers.qr', $driver) }}" alt="QR code for {{ $displayName }}">
                <div class="small text-muted mt-3">QR Status: <strong>{{ $driver->qrCode->status }}</strong></div>
                <code class="d-block mt-2">{{ $driver->qrCode->qr_token }}</code>
            </div>
            <div class="modal-footer justify-content-center">
                <a class="btn btn-outline-secondary" href="{{ route('drivers.qr', ['driver' => $driver, 'download' => 1]) }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i> Download QR
                </a>
                <button class="btn btn-outline-secondary" type="button" onclick="window.open('{{ route('drivers.qr', $driver) }}', '_blank')?.print()">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i> Print QR
                </button>
                @if ($driver->qrCode->status === 'Active')
                    <form method="POST" action="{{ route('drivers.qr.deactivate', $driver) }}">
                        @csrf
                        <button class="btn btn-outline-danger" type="submit">Deactivate</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('drivers.qr.activate', $driver) }}">
                        @csrf
                        <button class="btn btn-outline-success" type="submit">Activate</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('drivers.qr.regenerate', $driver) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" onclick="return confirm('Regenerate this QR code? The current code will stop working.')">
                        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Regenerate QR
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>