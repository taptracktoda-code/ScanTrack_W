@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4 mb-lg-5">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <p class="section-kicker mb-2">Administration</p>
                
                <p class="dashboard-page-subtitle mb-0">Update your personal and account details.</p>
            </div>
        </div>
    </section>

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="card dashboard-card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="mb-4 border-bottom border-200 pb-3">
                <h3 class="section-title mb-1">Profile Settings</h3>
                <p class="text-muted mb-0">Update your personal and account details.</p>
            </div>

            <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data">
                @csrf

                <div class="row g-4">
                    <div class="col-12 col-xl-4">
                        <div class="card border-0 bg-white shadow-sm h-100">
                            <div class="card-body p-3">
                                <label for="profile_picture" class="form-label">Profile picture</label>
                                <input id="profile_picture" name="profile_picture" type="file" accept="image/*" class="form-control @error('profile_picture') is-invalid @enderror">
                                @error('profile_picture')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if (!empty($user->profile_picture))
                                    <div class="mt-3 d-flex justify-content-center">
                                        <img src="{{ asset('storage/' . $user->profile_picture) }}" alt="Current profile" class="rounded-circle" style="width: 84px; height: 84px; object-fit: cover;">
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-8">
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label for="name" class="form-label">Full name</label>
                                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="form-control @error('name') is-invalid @enderror">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-6">
                                <label for="email" class="form-label">Email address</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="form-control @error('email') is-invalid @enderror">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-6">
                                <label for="unit" class="form-label">Unit</label>
                                <select id="unit" name="unit" required class="form-control @error('unit') is-invalid @enderror">
                                    <option value="Main unit" @selected(old('unit', $user->unit) === 'Main unit')>Main unit</option>
                                    <option value="Unit 1" @selected(old('unit', $user->unit) === 'Unit 1')>Unit 1</option>
                                    <option value="Unit 2" @selected(old('unit', $user->unit) === 'Unit 2')>Unit 2</option>
                                </select>
                                @error('unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-6">
                                <label for="contact" class="form-label">Contact number</label>
                                <input id="contact" name="contact" type="text" value="{{ old('contact', $user->contact) }}" class="form-control @error('contact') is-invalid @enderror">
                                @error('contact')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $user->address) }}</textarea>
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection
