<div class="sidebar-panel h-100 d-flex flex-column justify-content-between">
    <div>
        <div class="sidebar-header d-flex align-items-start justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                
                <!-- Logo -->
               <div class="sidebar-logo">
    <img src="{{ asset('images/logo for toda.png') }}"
         alt="TODA Terminal Management Logo">
</div>

                <!-- System Name -->
                <div>
                    <div class="sidebar-brand">TODA Terminal Management</div>
                    <div class="sidebar-subtitle">Terminal Operations</div>
                </div>
            </div>

            @unless ($mobile)
                <button type="button"
                        class="sidebar-collapse-toggle"
                        data-sidebar-collapse-toggle
                        aria-label="Toggle sidebar"
                        aria-pressed="false">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
            @endunless
        </div>

        <div class="sidebar-profile mt-4 d-flex align-items-center gap-3">
            <div class="sidebar-avatar" style="width: 56px; height: 56px; border-radius: 50%; overflow: hidden; display: inline-flex; align-items: center; justify-content: center; background: #f1f3f5;">
                @if (!empty(auth()->user()?->profile_picture))
                    <img src="{{ asset('storage/' . auth()->user()->profile_picture) }}" alt="Profile picture" style="width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 50%;">
                @else
                    <span style="font-weight: 700; color: #2c3e50;">{{ strtoupper(substr($userName, 0, 2)) }}</span>
                @endif
            </div>
            <div>
                <div class="sidebar-profile-name">{{ $userName }}</div>
            </div>
        </div>

        
        <nav class="sidebar-nav mt-3">
            @foreach ($menuItems as $item)
                @php
                    $isActive = request()->routeIs($item['route']);
                    $href = route($item['route']);
                @endphp

                <a href="{{ $href }}" class="sidebar-link {{ $isActive ? 'active' : '' }}">
                    <i class="bi {{ $item['icon'] }}"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>
    <div class="sidebar-footer mt-4">
        <a href="{{ route('settings.profile') }}" class="sidebar-link {{ request()->routeIs('settings.profile') ? 'active' : '' }}">
            <i class="bi bi-person-gear"></i>
            <span>Admin Profile</span>
        </a>
        <form action="{{ route('logout') }}" method="POST" class="mt-2">
            @csrf
            <button type="submit" class="sidebar-link sidebar-action logout-link w-100">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</div>