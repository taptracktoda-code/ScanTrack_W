@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">System administration</p>
            <h2 class="dashboard-page-title mb-2">Manager Accounts</h2>
            <p class="dashboard-page-subtitle mb-0">Create accounts, assign organizations, and manage mobile access.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card dashboard-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="section-title mb-3">Create Manager</h3>
            <form method="POST" action="{{ route('managers.store') }}" class="row g-3">
                @csrf
                <div class="col-12 col-md-4 col-xl-2">
                    <label for="manager-first-name" class="form-label">First name</label>
                    <input id="manager-first-name" name="first_name" value="{{ old('first_name') }}" class="form-control" required maxlength="80">
                </div>
                <div class="col-12 col-md-4 col-xl-1">
                    <label for="manager-middle-name" class="form-label">Middle</label>
                    <input id="manager-middle-name" name="middle_name" value="{{ old('middle_name') }}" class="form-control" maxlength="80">
                </div>
                <div class="col-12 col-md-4 col-xl-2">
                    <label for="manager-last-name" class="form-label">Last name</label>
                    <input id="manager-last-name" name="last_name" value="{{ old('last_name') }}" class="form-control" required maxlength="80">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="manager-email" class="form-label">Email</label>
                    <input id="manager-email" name="email" type="email" value="{{ old('email') }}" class="form-control" required maxlength="255">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="manager-contact" class="form-label">Contact number</label>
                    <input id="manager-contact" name="contact" value="{{ old('contact') }}" class="form-control" required maxlength="40">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="manager-organization" class="form-label">Organization</label>
                    <select id="manager-organization" name="organization_id" class="form-select" required>
                        <option value="">Choose organization</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-xl-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" aria-label="Create manager"><i class="bi bi-person-plus"></i></button>
                </div>
            </form>
        </div>
    </section>

    <section class="card dashboard-card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="text-uppercase small text-muted">
                        <tr><th class="ps-4">Manager</th><th>Contact</th><th>Organization</th><th>Status</th><th>Last activity</th><th class="text-end pe-4">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($managers as $manager)
                            <tr>
                                <td class="ps-4">
                                    <form id="manager-{{ $manager->id }}-update" method="POST" action="{{ route('managers.update', $manager) }}">@csrf @method('PUT')</form>
                                    <input form="manager-{{ $manager->id }}-update" class="form-control form-control-sm mb-1" name="first_name" value="{{ $manager->first_name }}" aria-label="Manager first name" required>
                                    <input form="manager-{{ $manager->id }}-update" class="form-control form-control-sm mb-1" name="middle_name" value="{{ $manager->middle_name }}" aria-label="Manager middle name">
                                    <input form="manager-{{ $manager->id }}-update" class="form-control form-control-sm mb-1" name="last_name" value="{{ $manager->last_name }}" aria-label="Manager last name" required>
                                    <input form="manager-{{ $manager->id }}-update" class="form-control form-control-sm" type="email" name="email" value="{{ $manager->email }}" aria-label="Manager email" required>
                                </td>
                                <td><input form="manager-{{ $manager->id }}-update" class="form-control form-control-sm" name="contact" value="{{ $manager->contact }}" aria-label="Manager contact" required></td>
                                <td>
                                    <select form="manager-{{ $manager->id }}-update" class="form-select form-select-sm" name="organization_id" aria-label="Assigned organization" required>
                                        @foreach ($organizations as $organization)
                                            <option value="{{ $organization->id }}" @selected($manager->organization_id == $organization->id)>{{ $organization->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><span class="badge {{ strcasecmp((string) $manager->status, 'Active') === 0 ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $manager->status }}</span></td>
                                <td>{{ $manager->terminal_activities_max_created_at ? \Illuminate\Support\Carbon::parse($manager->terminal_activities_max_created_at)->diffForHumans() : 'No mobile activity' }}</td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        <button form="manager-{{ $manager->id }}-update" class="btn btn-sm btn-outline-primary" title="Save manager details" aria-label="Save manager details"><i class="bi bi-check-lg"></i></button>
                                        <form method="POST" action="{{ route('managers.status', $manager) }}">@csrf<button class="btn btn-sm btn-outline-secondary" title="Toggle account status" aria-label="Toggle account status"><i class="bi bi-toggle-on"></i></button></form>
                                        <form method="POST" action="{{ route('managers.password.reset', $manager) }}">@csrf<button class="btn btn-sm btn-outline-warning" title="Reset password and email new credentials" aria-label="Reset manager password"><i class="bi bi-key"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No manager accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $managers->links() }}</div>
        </div>
    </section>
@endsection