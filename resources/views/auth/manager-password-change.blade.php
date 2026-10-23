@extends('layouts.auth')

@section('content')
    <div class="auth-heading">
        <p class="auth-eyebrow">Secure your account</p>
        <h2>Change your temporary password</h2>
        <p>Choose a new password before managing your TODA schedule.</p>
    </div>

    <form method="POST" action="{{ route('manager.password.update') }}">
        @csrf

        <div class="auth-field">
            <label for="current-password">Temporary password</label>
            <input id="current-password" type="password" name="current_password" required autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror">
            @error('current_password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password-confirmation">Confirm new password</label>
            <input id="password-confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="form-control">
        </div>

        <button type="submit" class="auth-submit">Change password <span aria-hidden="true">&rarr;</span></button>
    </form>
@endsection
