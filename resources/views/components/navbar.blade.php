@php
    $currentDate = \Illuminate\Support\Carbon::now()->format('l, F j, Y');
@endphp

<div class="topbar-wrap">
    <nav class="dashboard-navbar navbar navbar-expand-lg">
        <div class="container-fluid px-0 d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none nav-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                <i class="bi bi-list"></i>
            </button>

            <div class="me-auto">
                <div class="text-muted small fw-semibold">{{ $currentDate }}</div>
                <h1 class="navbar-title mb-0">Dashboard</h1>
            </div>

            <div class="navbar-actions d-flex align-items-center gap-2 gap-md-3">
                @if (auth()->user()?->isSuperAdmin() || auth()->user()?->isManager())
                    <div class="dropdown">
                        <button id="navbar-notifications-toggle" type="button" class="btn btn-light nav-icon-button position-relative navbar-notifications-button" aria-label="Notifications" title="Notifications" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span id="navbar-notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light d-none">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-0 shadow" aria-labelledby="navbar-notifications-toggle" style="width: min(22rem, calc(100vw - 2rem));">
                            <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                                <h2 class="h6 mb-0">Notifications</h2>
                                <button id="notifications-mark-read" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">Mark all read</button>
                            </div>
                            <div id="navbar-notification-list" class="overflow-auto" style="max-height: 22rem;" aria-live="polite">
                                <div class="px-3 py-4 text-center text-muted small">Loading notifications...</div>
                            </div>
                        </div>
                    </div>
                @endif
                <a href="{{ route(auth()->user()?->isManager() ? 'manager.profile' : 'settings.profile') }}" class="profile-chip d-flex align-items-center gap-2 text-decoration-none text-reset" title="{{ auth()->user()?->isManager() ? 'Manager profile' : 'Go to profile settings' }}">
                    <span class="profile-chip-icon" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; background: #f1f3f5;">
                        @if (!empty(auth()->user()?->profile_picture))
                            <img src="{{ asset('storage/' . auth()->user()->profile_picture) }}" alt="Profile picture" style="width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 50%;">
                        @else
                            <i class="bi bi-person-circle"></i>
                        @endif
                    </span>
                    <div class="d-none d-md-block">
                        <div class="profile-chip-name">{{ auth()->user()->name ?? 'Juan Dela Cruz' }}</div>
                        <div class="profile-chip-role">{{ auth()->user()->role ?? 'User' }}</div>
                    </div>
                </a>
            </div>
        </div>
    </nav>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const notificationBadge = document.getElementById('navbar-notification-badge');
                const notificationList = document.getElementById('navbar-notification-list');
                const notificationEndpoint = @json(route(auth()->user()?->isManager() ? 'manager.notifications.feed' : 'notifications.feed'));
                const notificationStorageKey = @json('dashboardLastReadActivityTime:'.auth()->id());
                let latestNotificationId = 0;

                const refreshNotifications = async () => {
                    if (!notificationBadge || !notificationList) return;

                    try {
                        const response = await fetch(notificationEndpoint, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!response.ok) return;

                        const payload = await response.json();
                        const notifications = payload.notifications || [];
                        latestNotificationId = Math.max(0, ...notifications.map((item) => Number(item.read_key ?? item.id) || 0));
                        const lastReadId = Number(window.localStorage.getItem(notificationStorageKey) || 0);
                        const unreadCount = notifications.filter((item) => Number(item.read_key ?? item.id) > lastReadId).length;
                        notificationBadge.textContent = String(unreadCount);
                        notificationBadge.classList.toggle('d-none', unreadCount === 0);
                        document.getElementById('navbar-notifications-toggle')?.setAttribute(
                            'aria-label',
                            unreadCount ? `Notifications, ${unreadCount} unread` : 'Notifications'
                        );

                        notificationList.replaceChildren();
                        if (!notifications.length) {
                            const empty = document.createElement('div');
                            empty.className = 'px-3 py-4 text-center text-muted small';
                            empty.textContent = 'No recent activity.';
                            notificationList.append(empty);
                            return;
                        }

                        notifications.forEach((item) => {
                            const row = document.createElement(item.url ? 'a' : 'div');
                            row.className = 'd-block text-decoration-none text-reset px-3 py-3 border-bottom';
                            if (item.url) row.href = item.url;
                            const title = document.createElement('div');
                            title.className = 'fw-semibold small';
                            title.textContent = item.title || 'System activity';
                            const detail = document.createElement('div');
                            detail.className = 'text-muted small mt-1';
                            detail.textContent = item.description || '';
                            const time = document.createElement('div');
                            time.className = 'text-muted small mt-1';
                            time.textContent = item.created_at || '';
                            row.append(title, detail, time);
                            notificationList.append(row);
                        });
                    } catch (error) {
                        console.warn('Unable to refresh notifications.', error);
                    }
                };

                document.getElementById('notifications-mark-read')?.addEventListener('click', () => {
                    if (latestNotificationId) {
                        window.localStorage.setItem(notificationStorageKey, String(latestNotificationId));
                    }
                    notificationBadge?.classList.add('d-none');
                    notificationBadge.textContent = '0';
                });

                refreshNotifications();
                window.setInterval(refreshNotifications, 10000);
            });
        </script>
    @endpush
</div>
