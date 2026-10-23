@extends('layouts.app')

@section('content')
    <section class="dashboard-hero mb-4">
        <div>
            <p class="section-kicker mb-2">Account settings</p>
            <h1 class="dashboard-page-title mb-2">Manager Profile</h1>
            <p class="dashboard-page-subtitle mb-0">Update your account details.</p>
        </div>
    </section>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <section class="card dashboard-card border-0 shadow-sm"><div class="card-body p-4">
        <form method="POST" action="{{ route('manager.profile.update') }}" class="row g-3">
            @csrf @method('PUT')
            @foreach (['first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'email' => 'Email', 'contact' => 'Contact number', 'address' => 'Address'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label" for="profile-{{ $field }}">{{ $label }}</label>
                    @if ($field === 'address')
                        <textarea class="form-control" id="profile-{{ $field }}" name="{{ $field }}" rows="3">{{ old($field, $manager->{$field}) }}</textarea>
                    @else
                        <input class="form-control" id="profile-{{ $field }}" name="{{ $field }}" value="{{ old($field, $manager->{$field}) }}" @if ($field === 'email') type="email" @endif @if ($field !== 'middle_name') required @endif>
                    @endif
                </div>
            @endforeach
            <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Save profile</button></div>
        </form>
    </div></section>
@endsection
