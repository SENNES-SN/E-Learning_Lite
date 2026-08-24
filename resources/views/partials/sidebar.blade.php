@php
    $activeNav = $activeNav ?? null;
    $activeCourseId = (int) (
        $courseId
        ?? request()->route('courseId')
        ?? request()->query('courseid')
        ?? session('active_course_id', 0)
    );
    $courseLearningUrl = $activeCourseId > 0
        ? route('courses.show', ['courseId' => $activeCourseId])
        : route('dashboard').'#courses';
    $navItems = [
        'dashboard' => ['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'home'],
        'moodle' => ['label' => 'Kursus Pembelajaran', 'url' => $courseLearningUrl, 'icon' => 'book'],
        'notifications' => ['label' => 'Notifikasi', 'url' => route('notifications'), 'icon' => 'bell'],
        'profile' => ['label' => 'Profil Pengguna', 'url' => route('profile'), 'icon' => 'user'],
    ];
@endphp

<aside class="app-sidebar" aria-label="Navigasi utama">
    <a class="app-brand" href="{{ route('dashboard') }}" aria-label="E-Learning Lite - Dashboard">
        <span class="app-brand-mark" aria-hidden="true"><i data-lucide="graduation-cap"></i></span>
        <span>E - Learning Lite</span>
    </a>

    <nav class="app-nav-list">
        @foreach ($navItems as $key => $item)
            <a
                class="app-nav-item {{ $activeNav === $key ? 'active' : '' }}"
                href="{{ $item['url'] }}"
                @if ($activeNav === $key) aria-current="page" @endif
            >
                <span class="asset-icon asset-icon-{{ $item['icon'] }}" aria-hidden="true"></span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <form class="sidebar-logout-form" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="app-logout-button" data-loading-button data-loading-tone="dark">
            <span class="asset-icon asset-icon-logout" aria-hidden="true"></span>
            <span>Logout</span>
        </button>
    </form>
</aside>

<button type="button" class="app-sidebar-toggle app-sidebar-close" data-sidebar-toggle="close" aria-label="Tutup navigasi">
    <i data-lucide="x"></i>
</button>
<button type="button" class="app-sidebar-toggle app-sidebar-open" data-sidebar-toggle="open" aria-label="Buka navigasi">
    <i data-lucide="menu"></i>
</button>

<script>
    (function () {
        const collapsedClass = 'sidebar-collapsed';
        const mobileQuery = window.matchMedia('(max-width: 820px)');

        function setCollapsed(isCollapsed) {
            document.body.classList.toggle(collapsedClass, isCollapsed);
        }

        setCollapsed(mobileQuery.matches);

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                setCollapsed(button.dataset.sidebarToggle === 'close');
            });
        });

        mobileQuery.addEventListener('change', (event) => setCollapsed(event.matches));
    })();
</script>
