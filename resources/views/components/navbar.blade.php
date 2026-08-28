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
                <button class="btn btn-light nav-icon-button" type="button" aria-label="Send SMS" data-bs-toggle="modal" data-bs-target="#sendSmsModal">
                    <i class="bi bi-chat-left-text"></i>
                </button>
                <a href="{{ route('notifications') }}" class="btn btn-light nav-icon-button position-relative navbar-notifications-button" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span id="navbar-notification-badge" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none">
                        <span id="navbar-notification-count" class="visually-hidden">0</span>
                    </span>
                </a>
                <a href="{{ route('settings.profile') }}" class="profile-chip d-flex align-items-center gap-2 text-decoration-none text-reset" title="Go to profile settings">
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

    <div class="modal fade" id="sendSmsModal" tabindex="-1" aria-labelledby="sendSmsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="sendSmsForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="sendSmsModalLabel">Send SMS to Driver</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="smsDriver" class="form-label">Select Driver</label>
                        <select id="smsDriver" name="driver_id" class="form-select" required>
                            <option value="">Loading drivers...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="smsMessage" class="form-label">Message</label>
                        <textarea id="smsMessage" name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="text-muted small" id="smsContactPreview">Contact number will appear once a driver is selected.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn driver-primary-button">Send SMS</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('sendSmsModal');
                const driverSelect = document.getElementById('smsDriver');
                const contactPreview = document.getElementById('smsContactPreview');
                const sendSmsForm = document.getElementById('sendSmsForm');

                const loadDrivers = async () => {
                    driverSelect.innerHTML = '<option value="">Loading drivers...</option>';
                    contactPreview.textContent = 'Contact number will appear once a driver is selected.';

                    try {
                        const response = await fetch('{{ route('drivers.options') }}', {
                            headers: { Accept: 'application/json' },
                        });
                        const payload = await response.json();

                        if (!payload.drivers || payload.drivers.length === 0) {
                            driverSelect.innerHTML = '<option value="">No drivers available</option>';
                            return;
                        }

                        driverSelect.innerHTML = '<option value="">Select a driver</option>' +
                            payload.drivers.map(driver => `\
                                <option value="${driver.id}" data-contact="${driver.contact_number}">${driver.name} (${driver.contact_number || 'No contact'})</option>\
                            `).join('');
                    } catch (error) {
                        driverSelect.innerHTML = '<option value="">Unable to load drivers</option>';
                        console.error('Unable to load drivers for SMS modal.', error);
                    }
                };

                if (modal) {
                    modal.addEventListener('show.bs.modal', loadDrivers);
                }

                if (driverSelect) {
                    driverSelect.addEventListener('change', (event) => {
                        const selected = event.target.selectedOptions[0];
                        const contact = selected?.dataset?.contact || '';
                        contactPreview.textContent = contact ? `Contact: ${contact}` : 'Contact number will appear once a driver is selected.';

                        if (selected?.value) {
                            sendSmsForm.action = `/drivers/${selected.value}/sms`;
                        } else {
                            sendSmsForm.action = '';
                        }
                    });
                }

                const notificationBadge = document.getElementById('navbar-notification-badge');
                const notificationCount = document.getElementById('navbar-notification-count');
                const notificationEndpoint = @json(route('dashboard.stats'));
                const notificationStorageKey = 'dashboardNotificationCount';

                const updateNotification = (count) => {
                    const badgeVisible = Number(count) > 0;
                    notificationBadge.classList.toggle('d-none', !badgeVisible);
                    notificationCount.textContent = badgeVisible ? String(count) : '0';
                };

                const trackNewNotifications = (count) => {
                    try {
                        const previous = Number(window.localStorage.getItem(notificationStorageKey) || 0);
                        if (count > previous) {
                            notificationBadge.classList.remove('bg-secondary');
                            notificationBadge.classList.add('bg-danger');
                        }
                        window.localStorage.setItem(notificationStorageKey, String(count));
                    } catch {
                        // ignore storage errors
                    }
                };

                const refreshNotifications = async () => {
                    try {
                        const response = await fetch(notificationEndpoint, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        if (!response.ok) {
                            return;
                        }
                        const payload = await response.json();
                        const count = payload.stats?.pending_logs?.value ?? 0;
                        updateNotification(count);
                        trackNewNotifications(count);
                    } catch (error) {
                        console.warn('Unable to refresh notifications.', error);
                    }
                };

                refreshNotifications();
                window.setInterval(refreshNotifications, 10000);
            });
        </script>
    @endpush
</div>