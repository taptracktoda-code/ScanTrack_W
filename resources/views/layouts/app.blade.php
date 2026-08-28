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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
</head>
<body>
    <div class="dashboard-shell">
        @include('components.sidebar', ['mobile' => false])
        @include('components.sidebar', ['mobile' => true])

        <main class="dashboard-main">
            @include('components.navbar')

            <div class="dashboard-content">
                @yield('content')
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const shell = document.querySelector('.dashboard-shell');
            if (!shell) {
                return;
            }

            const storageKey = 'dashboard-sidebar-collapsed';
            const collapseButtons = shell.querySelectorAll('[data-sidebar-collapse-toggle]');
            const sidebarPanels = shell.querySelectorAll('.sidebar-panel');

            const applyState = (collapsed) => {
                shell.classList.toggle('sidebar-collapsed', collapsed);
                collapseButtons.forEach((button) => {
                    button.setAttribute('aria-pressed', String(collapsed));
                    button.innerHTML = collapsed ? '<i class="bi bi-layout-sidebar-inset-reverse"></i>' : '<i class="bi bi-layout-sidebar-inset"></i>';
                });
            };

            const getPanelStorageKey = (panel) => panel.closest('.dashboard-sidebar-mobile') ? 'dashboard-sidebar-scroll-mobile' : 'dashboard-sidebar-scroll-desktop';
            const persistSidebarScroll = () => {
                sidebarPanels.forEach((panel) => {
                    sessionStorage.setItem(getPanelStorageKey(panel), String(panel.scrollTop));
                });
            };

            const restoreSidebarScroll = () => {
                sidebarPanels.forEach((panel) => {
                    const key = getPanelStorageKey(panel);
                    const saved = sessionStorage.getItem(key);

                    if (saved !== null) {
                        panel.scrollTop = parseInt(saved, 10) || 0;
                    }
                });
            };

            applyState(localStorage.getItem(storageKey) === '1');

            collapseButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const collapsed = !shell.classList.contains('sidebar-collapsed');
                    localStorage.setItem(storageKey, collapsed ? '1' : '0');
                    applyState(collapsed);
                });
            });

            sidebarPanels.forEach((panel) => {
                panel.addEventListener('scroll', persistSidebarScroll, { passive: true });
            });

            shell.querySelectorAll('.sidebar-link, .logout-link').forEach((link) => {
                link.addEventListener('click', persistSidebarScroll);
            });

            window.addEventListener('pageshow', restoreSidebarScroll);
            window.addEventListener('load', restoreSidebarScroll);
            requestAnimationFrame(restoreSidebarScroll);
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deleteForms = document.querySelectorAll('form[data-swal-delete]');
            const theme = getComputedStyle(document.documentElement);
            const primaryColor = theme.getPropertyValue('--dashboard-blue').trim() || '#1138d4';
            const textColor = theme.getPropertyValue('--dashboard-text').trim() || '#172033';
            const cardColor = theme.getPropertyValue('--dashboard-bg').trim() || '#f4f7fb';

            deleteForms.forEach((form) => {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const itemName = form.dataset.swalName || 'this item';

                    Swal.fire({
                        title: `Delete ${itemName}?`,
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        iconColor: primaryColor,
                        background: cardColor,
                        color: textColor,
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true,
                        focusCancel: true,
                        customClass: {
                            popup: 'swal2-theme-dashboard',
                            title: 'swal2-theme-dashboard-title',
                            htmlContainer: 'swal2-theme-dashboard-text',
                            confirmButton: 'swal2-theme-dashboard-confirm',
                            cancelButton: 'swal2-theme-dashboard-cancel',
                            actions: 'swal2-theme-dashboard-actions',
                        },
                        buttonsStyling: false,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            @if (session('status'))
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: @json(session('status')),
                    showConfirmButton: false,
                    background: primaryColor,
                    color: '#ffffff',
                    iconColor: '#ffffff',
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'swal2-theme-toast',
                    },
                });
            @endif
        });
    </script>
    @stack('scripts')
</body>
</html>