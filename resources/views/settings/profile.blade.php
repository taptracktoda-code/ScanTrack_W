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
                                <input id="profile_picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('profile_picture') is-invalid @enderror">
                                @error('profile_picture')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div id="profile-picture-crop-error" class="invalid-feedback d-block" role="alert"></div>
                                <div class="mt-3 d-flex justify-content-center">
                                    <img
                                        id="profile-picture-preview"
                                        src="{{ $user->profile_picture ? asset('storage/' . $user->profile_picture) : '' }}"
                                        alt="Profile picture preview"
                                        class="rounded-circle {{ $user->profile_picture ? '' : 'd-none' }}"
                                        style="width: 112px; height: 112px; object-fit: cover;"
                                    >
                                    <div id="profile-picture-placeholder" class="rounded-circle d-flex align-items-center justify-content-center bg-light text-muted {{ $user->profile_picture ? 'd-none' : '' }}" style="width: 112px; height: 112px;">
                                        <i class="bi bi-person fs-1" aria-hidden="true"></i>
                                    </div>
                                </div>
                                <small class="form-text d-block mt-2">JPG, PNG, or WebP. Maximum size 2 MB.</small>
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

    <div class="modal fade" id="profile-picture-crop-modal" tabindex="-1" aria-labelledby="profile-picture-crop-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="profile-picture-crop-title">Crop profile picture</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="profile-crop-stage">
                        <img id="profile-picture-crop-image" alt="Selected image to crop">
                    </div>
                    <div class="d-flex justify-content-center gap-2 mt-3" aria-label="Crop controls">
                        <button type="button" class="btn btn-light" data-crop-zoom="-0.1" title="Zoom out" aria-label="Zoom out"><i class="bi bi-zoom-out"></i></button>
                        <button type="button" class="btn btn-light" data-crop-zoom="0.1" title="Zoom in" aria-label="Zoom in"><i class="bi bi-zoom-in"></i></button>
                        <button type="button" class="btn btn-light" id="profile-picture-crop-reset" title="Reset crop" aria-label="Reset crop"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="profile-picture-crop-apply"><i class="bi bi-crop me-2"></i>Use cropped picture</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/profile-picture-cropper.js')
@endpush

@push('styles')
    <style>
        .profile-crop-stage {
            width: 100%;
            height: min(62vh, 560px);
            overflow: hidden;
            background: #20262d;
        }

        .profile-crop-stage img {
            display: block;
            max-width: 100%;
        }
    </style>
@endpush
