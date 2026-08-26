@php
    $topbarUnreadCount = (int) ($unreadNotificationCount ?? 0);
@endphp

<header class="student-topbar" aria-label="Aksi akun">
    <a
        class="student-topbar-action"
        href="{{ route('notifications') }}"
        aria-label="{{ $topbarUnreadCount > 0 ? 'Buka notifikasi, '.$topbarUnreadCount.' belum dibaca' : 'Buka notifikasi' }}"
        data-notification-summary-url="{{ route('notifications.unread-summary') }}"
    >
        <span class="asset-icon asset-icon-bell" aria-hidden="true"></span>
        <span
            class="student-topbar-badge"
            aria-hidden="true"
            data-notification-badge
            @if ($topbarUnreadCount <= 0) hidden @endif
        >{{ $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount }}</span>
    </a>
    <a class="student-topbar-action" href="{{ route('profile') }}" aria-label="Buka profil pengguna">
        <span class="asset-icon asset-icon-user" aria-hidden="true"></span>
    </a>
</header>
