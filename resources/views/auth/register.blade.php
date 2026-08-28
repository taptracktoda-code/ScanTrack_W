@extends('layouts.auth')

@section('content')
    <div class="text-center mb-4">
        <h1 class="h4 mb-1">Create a new account</h1>
        <p class="text-muted mb-0">Register to access the terminal management dashboard.</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label for="full_name" class="form-label">Full name</label>
            <input id="full_name" type="text" name="full_name" value="{{ old('full_name') }}" required autofocus class="form-control @error('full_name') is-invalid @enderror">
            @error('full_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="unit" class="form-label">Unit</label>
            <select id="unit" name="unit" required class="form-control @error('unit') is-invalid @enderror">
                <option value="" disabled selected>Select a unit</option>
                <option value="Main unit" @selected(old('unit') === 'Main unit')>Main unit</option>
                <option value="Unit 1" @selected(old('unit') === 'Unit 1')>Unit 1</option>
                <option value="Unit 2" @selected(old('unit') === 'Unit 2')>Unit 2</option>
            </select>
            @error('unit')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" required class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="form-control">
        </div>

        <button type="submit" class="btn btn-primary w-100">Register</button>

        <div class="text-center mt-3 text-muted small">
            Already have an account? <a href="{{ route('login') }}">Log in</a>
        </div>
    </form>
@endsection
