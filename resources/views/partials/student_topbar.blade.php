@php
    $topbarUnreadCount = (int) ($unreadNotificationCount ?? 0);
@endphp

<header class="student-topbar" aria-label="Aksi akun">
    <a class="student-topbar-action" href="{{ route('notifications') }}" aria-label="Buka notifikasi">
        <span class="asset-icon asset-icon-bell" aria-hidden="true"></span>
        @if ($topbarUnreadCount > 0)
            <span class="student-topbar-badge" aria-hidden="true">
                {{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}
            </span>
        @endif
    </a>
    <a class="student-topbar-action" href="{{ route('profile') }}" aria-label="Buka profil pengguna">
        <span class="asset-icon asset-icon-user" aria-hidden="true"></span>
    </a>
</header>
