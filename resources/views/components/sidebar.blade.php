@php
    $menuItems = auth()->user()?->isManager()
        ? [
            ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'manager.dashboard'],
            ['label' => 'Drivers', 'icon' => 'bi-person-badge', 'route' => 'manager.drivers'],
            ['label' => 'Vehicles', 'icon' => 'bi-truck', 'route' => 'manager.vehicles'],
            ['label' => 'QR Management', 'icon' => 'bi-qr-code-scan', 'route' => 'manager.qr.management'],
            ['label' => 'Attendance', 'icon' => 'bi-calendar-check', 'route' => 'manager.attendance'],
            ['label' => 'Reports', 'icon' => 'bi-file-earmark-text', 'route' => 'manager.reports'],
            ['label' => 'Organization', 'icon' => 'bi-buildings', 'route' => 'manager.organization'],
        ]
        : [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'dashboard'],
        ['label' => 'Drivers', 'icon' => 'bi-person-badge', 'route' => 'drivers'],
        ['label' => 'Managers', 'icon' => 'bi-people', 'route' => 'managers'],
        ['label' => 'Queue Monitoring', 'icon' => 'bi-list-ol', 'route' => 'queues.monitoring'],
        ['label' => 'Organizations', 'icon' => 'bi-buildings', 'route' => 'settings.toda_organizations'],
        ['label' => 'Vehicles', 'icon' => 'bi-truck', 'route' => 'vehicles'],
        ['label' => 'QR Management', 'icon' => 'bi-qr-code-scan', 'route' => 'qr.management'],
        ['label' => 'Attendance', 'icon' => 'bi-calendar-check', 'route' => 'attendance'],
        ['label' => 'Reports', 'icon' => 'bi-file-earmark-text', 'route' => 'reports'],
        ['label' => 'Terminal Activities', 'icon' => 'bi-activity', 'route' => 'terminal.activities'],
        ];

    $userName = auth()->user()->name ?? 'Juan Dela Cruz';
@endphp

@if ($mobile)
    <div class="offcanvas offcanvas-start dashboard-sidebar-mobile" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header px-4 pt-4">
            <div>
                <div class="sidebar-logo">
                    <img src="{{ asset('images/terminal-management-logo.svg') }}" alt="Terminal Management logo">
                </div>
                <div class="mt-3">
                    <p class="sidebar-brand mb-0">Terminal Management</p>
                    <small class="text-muted">Terminal Management</small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body px-4 pb-4 pt-0">
            @include('components.sidebar-menu', ['menuItems' => $menuItems, 'userName' => $userName])
        </div>
    </div>
@else
    <aside class="dashboard-sidebar d-none d-lg-flex flex-column">
        @include('components.sidebar-menu', ['menuItems' => $menuItems, 'userName' => $userName])
    </aside>
@endif
