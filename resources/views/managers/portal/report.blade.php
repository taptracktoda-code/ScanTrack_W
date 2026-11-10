@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div><p class="section-kicker mb-2">Driver reports</p><h1 class="dashboard-page-title mb-2">Report #{{ $issue->id }}</h1><p class="dashboard-page-subtitle mb-0">{{ $issue->driver?->name ?? 'Deleted driver' }}</p></div>
        <a class="btn btn-outline-primary" href="{{ route('manager.reports') }}">Back to reports</a>
    </section>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }} <a href="{{ route('manager.reports.show', $issue) }}">Refresh report</a></div>@endif
    <section class="card dashboard-card border-0 shadow-sm mb-4"><div class="card-body p-4">
        <dl class="row">
            <dt class="col-sm-3">Driver</dt><dd class="col-sm-9">{{ $issue->driver?->name ?? 'Deleted driver' }}</dd>
            <dt class="col-sm-3">Submitted (Manila)</dt><dd class="col-sm-9">{{ $issue->created_at->timezone('Asia/Manila')->format('F j, Y g:i A') }}</dd>
            <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ ['vehicle_breakdown' => 'Vehicle breakdown', 'leave_queue' => 'Request to leave queue', 'other' => 'Other concern'][$issue->type] ?? $issue->type }}</dd>
            <dt class="col-sm-3">Review status</dt><dd class="col-sm-9">{{ ucfirst($issue->status) }}</dd>
            <dt class="col-sm-3">Report details</dt><dd class="col-sm-9" style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $issue->explanation }}</dd>
            <dt class="col-sm-3">Original queue entry</dt><dd class="col-sm-9">{{ $issue->queue_entry_id ? '#'.$issue->queue_entry_id.' — '.($issue->queueEntry?->status ?? 'Unavailable') : 'General report' }}</dd>
            <dt class="col-sm-3">Attachments</dt><dd class="col-sm-9">
                @forelse ($issue->attachmentData() as $file)
                    @if (preg_match('/\.(jpe?g|png|webp)$/i', $file['name']))
                        <figure class="mb-3">
                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="View full-size photo: {{ $file['name'] }}">
                                <img src="{{ $file['url'] }}" alt="Report photo: {{ $file['name'] }}" class="img-fluid rounded border" style="max-height: 480px; object-fit: contain" loading="lazy" referrerpolicy="no-referrer">
                            </a>
                            <figcaption class="text-muted mt-1">{{ $file['name'] }} ({{ number_format($file['size'] / 1024) }} KB) &middot; Select photo to view full size</figcaption>
                        </figure>
                    @else
                        <a class="d-block mb-2" href="{{ $file['url'] }}">Download {{ $file['name'] }} ({{ number_format($file['size'] / 1024) }} KB)</a>
                    @endif
                @empty No attachments. @endforelse
                @if ($issue->attachments)<small class="text-muted">Attachment links expire after 15 minutes. If a photo does not load, refresh this page to try again.</small>@endif
            </dd>
            @if ($issue->decided_at)
                <dt class="col-sm-3">Reviewed by</dt><dd class="col-sm-9">{{ $issue->reviewer?->name ?? 'Manager' }} · {{ $issue->decided_at->timezone('Asia/Manila')->format('F j, Y g:i A') }}</dd>
                <dt class="col-sm-3">Review explanation</dt><dd class="col-sm-9" style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $issue->decision_reason }}</dd>
            @endif
        </dl>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('manager.reports.show', $issue) }}">Refresh report</a>
    </div></section>
    @if ($issue->status === 'pending')
        @php($canApprove = (int) $issue->driver?->organization_id === (int) $issue->organization_id && ($issue->queue_entry_id === null || $issue->queueEntry?->status === 'WAITING'))
        <section class="card dashboard-card border-0 shadow-sm"><div class="card-body p-4">
            <h2 class="h5">Review report</h2>
            @unless ($canApprove)<p class="alert alert-warning">This report is no longer eligible for approval. You can reject it with an explanation.</p>@endunless
            <form method="POST" action="{{ route('manager.reports.review', $issue) }}" data-loading-form>
                @csrf
                <label for="decision" class="form-label">Review status</label>
                <select class="form-select mb-3" id="decision" name="decision" required><option value="">Select a decision</option><option value="approved" @disabled(! $canApprove) @selected(old('decision') === 'approved')>Approved</option><option value="rejected" @selected(old('decision') === 'rejected')>Rejected</option></select>
                <label for="decision-reason" class="form-label">Review explanation (required)</label>
                <textarea class="form-control mb-3" id="decision-reason" name="decision_reason" rows="4" maxlength="500" required>{{ old('decision_reason') }}</textarea>
                @if ($issue->queue_entry_id !== null)
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_removal" value="1" id="confirm-removal" @checked(old('confirm_removal'))><label class="form-check-label" for="confirm-removal">If approving, I confirm removal of the driver from this waiting queue entry. The driver must check in again to rejoin.</label></div>
                @endif
                <button class="btn btn-primary" type="submit" data-busy-label="Saving review...">Save review</button>
            </form>
        </div></section>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/manager-reports.js') }}" defer></script>
@endpush
