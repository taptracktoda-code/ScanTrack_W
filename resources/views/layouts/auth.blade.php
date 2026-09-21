<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <div class="auth-brand-mark">
                <img src="{{ asset('images/terminal-management-logo.svg') }}" alt="Terminal Management logo">
            </div>
            <div>
                <p class="auth-kicker">Terminal operations</p>
                <h1>Keep every route moving.</h1>
                <p class="auth-brand-copy">A clear, reliable workspace for managing drivers, vehicles, and daily terminal activity.</p>
            </div>
            <div class="auth-status">
                <span class="auth-status-dot" aria-hidden="true"></span>
                <span>Operations platform</span>
            </div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <div class="auth-mobile-brand">
                    <img src="{{ asset('images/terminal-management-logo.svg') }}" alt="Terminal Management logo">
                    <span>Terminal Management</span>
                </div>

                @yield('content')

                <p class="auth-footer">Secure access for authorized personnel</p>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
