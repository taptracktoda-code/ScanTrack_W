@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Account settings</p>
            <h1 class="dashboard-page-title mb-2">Manager Profile</h1>
            <p class="dashboard-page-subtitle mb-0">Update your account details.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('manager.profile', ['edit' => 1]) }}">Edit Profile</a>
    </section>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card dashboard-card border-0 shadow-sm"><div class="card-body p-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            @if ($manager->profile_picture)
                <img src="{{ Storage::disk('public')->url($manager->profile_picture) }}" alt="Your profile photo" width="96" height="96" class="rounded-circle" style="object-fit: cover">
            @else
                <span class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center fs-1" style="width:96px;height:96px" aria-label="Profile initials">{{ mb_substr($manager->name, 0, 1) }}</span>
            @endif
            <div><h2 class="h4 mb-1">{{ $manager->name }}</h2><span class="text-muted">{{ $manager->email }}</span></div>
        </div>
        @if (request()->boolean('edit') || $errors->any())
        <form method="POST" enctype="multipart/form-data" action="{{ route('manager.profile.update') }}" class="row g-3" data-loading-form>
            @csrf @method('PUT')
            @foreach (['first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'email' => 'Email', 'contact' => 'Contact number', 'address' => 'Address'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label" for="profile-{{ $field }}">{{ $label }}</label>
                    @if ($field === 'address')
                        <textarea class="form-control" id="profile-{{ $field }}" name="{{ $field }}" rows="3">{{ old($field, $manager->{$field}) }}</textarea>
                    @else
                        <input class="form-control" id="profile-{{ $field }}" name="{{ $field }}" value="{{ old($field, $manager->{$field}) }}" @if ($field === 'email') type="email" @endif @if ($field !== 'middle_name') required @endif>
                    @endif
                    @error($field)<div class="text-danger small" role="alert">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="col-12">
                <label class="form-label" for="profile-picture">Profile photo</label>
                <input class="form-control" type="file" id="profile-picture" name="profile_picture" accept="image/jpeg,image/png,image/webp">
                <small class="text-muted">JPG, PNG or WebP. Maximum 2 MB. Choose your photo again if validation fails.</small>
                @error('profile_picture')<div class="text-danger small" role="alert">{{ $message }}</div>@enderror
                @if ($manager->profile_picture)<div class="form-check mt-2"><input class="form-check-input" id="remove-picture" name="remove_picture" value="1" type="checkbox" @checked(old('remove_picture'))><label class="form-check-label" for="remove-picture">Remove current photo</label></div>@endif
            </div>
            <div class="col-12 text-end"><a class="btn btn-outline-secondary me-2" href="{{ route('manager.profile') }}">Cancel</a><button class="btn btn-primary" type="submit" data-busy-label="Saving profile...">Save profile</button></div>
        </form>
        @else
            <dl class="row mb-0">
                @foreach (['name' => 'Name', 'email' => 'Email', 'contact' => 'Contact number', 'address' => 'Address'] as $field => $label)
                    <dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9">{{ $manager->{$field} ?: 'Not provided' }}</dd>
                @endforeach
            </dl>
        @endif
    </div></section>
@endsection

@push('scripts')
    <script src="{{ asset('js/manager-reports.js') }}" defer></script>
@endpush
