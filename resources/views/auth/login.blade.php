@extends('layouts.auth')

@section('content')
    <div class="auth-heading">
        <p class="auth-eyebrow">Welcome back</p>
        <h2>Sign in to your account</h2>
        <p>Enter your details to continue to the terminal dashboard.</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="auth-field">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your gmail" required autofocus class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Enter your password" required class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-options">
            <div class="form-check auth-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
        </div>

        <button type="submit" class="auth-submit">Log in <span aria-hidden="true">&rarr;</span></button>
    </form>

    <p class="small mt-4 mb-0">Are you a TODA manager? <a href="{{ route('manager.login') }}">Manager sign in</a></p>
@endsection
