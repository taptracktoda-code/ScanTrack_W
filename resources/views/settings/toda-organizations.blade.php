@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Administration</p>
                <h2 class="dashboard-page-title mb-2">TODA Organization Management</h2>
                <p class="dashboard-page-subtitle mb-0">Add new organizations and review the registered list in one place.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button id="toggleOrganizationForm" type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm">Add New Organization</button>
            </div>
        </div>
    </section>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
    @endif

    <section class="card dashboard-card border-0 mt-4 shadow-sm">
        <div class="card-body p-4">
            <div id="organizationFormContainer" class="mb-4 @if($errors->has('name') || $errors->has('barangay_travel_area')) d-block @else d-none @endif">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h3 class="section-title mb-1">Add new TODA Organization</h3>
                        <p class="text-muted mb-0">Create new organization records for the terminal network.</p>
                    </div>
                </div>

                <div class="card border-0 bg-white shadow-sm p-4">
                    <form method="POST" action="{{ route('settings.toda_organizations.organizations.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label for="name" class="form-label">Organization Name</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="form-control @error('name') is-invalid @enderror">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-6">
                                <label for="barangay_travel_area" class="form-label">Barangay Travel Area</label>
                                <input id="barangay_travel_area" name="barangay_travel_area" type="text" value="{{ old('barangay_travel_area') }}" required class="form-control @error('barangay_travel_area') is-invalid @enderror">
                                @error('barangay_travel_area')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Save organization</button>
                            <button id="cancelOrganizationForm" type="button" class="btn btn-outline-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive bg-white border rounded-4 shadow-sm p-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-uppercase small text-muted">
                        <tr>
                            <th class="border-top-0">#</th>
                            <th class="border-top-0">Organization Name</th>
                            <th class="border-top-0">Barangay Travel Area</th>
                            <th class="border-top-0">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organizations as $organization)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $organization->name }}</td>
                                <td>{{ $organization->barangay_travel_area }}</td>
                                <td>{{ $organization->created_at->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No TODA organizations have been added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleButton = document.getElementById('toggleOrganizationForm');
            const cancelButton = document.getElementById('cancelOrganizationForm');
            const formContainer = document.getElementById('organizationFormContainer');

            if (!toggleButton || !formContainer) {
                return;
            }

            toggleButton.addEventListener('click', () => {
                formContainer.classList.toggle('d-none');
                formContainer.classList.toggle('d-block');
            });

            if (cancelButton) {
                cancelButton.addEventListener('click', () => {
                    formContainer.classList.add('d-none');
                    formContainer.classList.remove('d-block');
                });
            }
        });
    </script>
@endpush
