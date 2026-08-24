<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Notifikasi aktivitas pembelajaran mahasiswa.">
    <title>Notifikasi - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page notification-final-page">
    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'notifications'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content notification-final-content">
                <header class="student-section-header">
                    <span class="student-section-header-icon" aria-hidden="true">
                        <i data-lucide="bell"></i>
                    </span>
                    <div>
                        <h1>Notifikasi</h1>
                        <p>Pantau aktivitas dan batas waktu pembelajaranmu.</p>
                    </div>
                    <a class="final-back-button" href="{{ route('dashboard') }}" aria-label="Kembali ke dashboard" data-loading-button data-loading-tone="dark">
                        <i data-lucide="undo-2" aria-hidden="true"></i>
                    </a>
                </header>

                <section class="notification-final-summary" aria-label="Ringkasan notifikasi">
                    <div>
                        <span class="notification-summary-icon" aria-hidden="true"><i data-lucide="bell-ring"></i></span>
                        <span><strong>{{ count($events ?? []) }}</strong> notifikasi</span>
                    </div>
                    <div>
                        <span class="notification-summary-icon" aria-hidden="true"><i data-lucide="calendar-clock"></i></span>
                        <span><strong>{{ $deadlineEventCount ?? 0 }}</strong> batas waktu</span>
                    </div>
                </section>

                <nav class="notification-final-filters" aria-label="Filter notifikasi">
                    <a class="{{ ($activeFilter ?? 'all') === 'all' ? 'active' : '' }}" href="{{ route('notifications') }}">Semua</a>
                    <a class="{{ ($activeFilter ?? 'all') === 'deadline' ? 'active' : '' }}" href="{{ route('notifications', ['filter' => 'deadline']) }}">Batas Waktu</a>
                </nav>

                @if ($notificationError)
                    <div class="student-inline-error" role="alert">
                        Notifikasi belum dapat dimuat. Silakan coba lagi beberapa saat.
                    </div>
                @endif

                <section class="notification-final-panel" aria-labelledby="notification-list-title">
                    <div class="notification-final-panel-heading">
                        <div>
                            <h2 id="notification-list-title">
                                {{ ($activeFilter ?? 'all') === 'deadline' ? 'Batas Waktu Terdekat' : 'Aktivitas Terbaru' }}
                            </h2>
                            <p>
                                {{ ($activeFilter ?? 'all') === 'deadline'
                                    ? 'Tugas dan kuis yang perlu segera diselesaikan.'
                                    : 'Pembaruan materi, tugas, kuis, dan diskusi mata kuliah.' }}
                            </p>
                        </div>
                    </div>

                    @if (! empty($events))
                        <div class="notification-final-list">
                            @foreach ($events as $event)
                                @php
                                    $isUnread = in_array($event['_notification_key'] ?? '', $unreadEventKeys ?? [], true);
                                    $moduleName = strtolower((string) ($event['modulename'] ?? ''));
                                    $eventType = strtolower((string) ($event['eventtype'] ?? 'aktivitas'));
                                    $eventLabel = match (true) {
                                        $eventType === 'materi' || in_array($moduleName, ['url', 'resource', 'page', 'book', 'folder', 'label'], true) => 'Materi',
                                        $eventType === 'tugas' || $moduleName === 'assign' => 'Tugas',
                                        $eventType === 'kuis' || $moduleName === 'quiz' => 'Kuis',
                                        in_array($moduleName, ['forum', 'chat'], true) => 'Diskusi',
                                        default => 'Aktivitas',
                                    };
                                    $eventIcon = match ($eventLabel) {
                                        'Materi' => 'book-open',
                                        'Tugas' => 'clipboard-list',
                                        'Kuis' => 'alarm-clock',
                                        'Diskusi' => 'messages-square',
                                        default => 'bell',
                                    };
                                    $time = $event['timesort'] ?? $event['timestart'] ?? null;
                                    $eventCourseId = (int) ($event['courseid'] ?? ($event['course']['id'] ?? 0));
                                    $eventModuleId = (int) ($event['cmid'] ?? 0);
                                    $eventUrl = $eventCourseId > 0 && $eventModuleId > 0
                                        ? route('courses.modules.show', ['courseId' => $eventCourseId, 'moduleId' => $eventModuleId])
                                        : ($eventCourseId > 0 ? route('courses.show', ['courseId' => $eventCourseId]) : null);
                                @endphp
                                <article class="notification-final-item {{ $isUnread ? 'is-unread' : '' }}">
                                    <span class="notification-final-item-icon" aria-hidden="true">
                                        <i data-lucide="{{ $eventIcon }}"></i>
                                    </span>
                                    <div class="notification-final-item-copy">
                                        <div class="notification-final-item-labels">
                                            <span>{{ $eventLabel }}</span>
                                            @if ($isUnread)<strong>Baru</strong>@endif
                                        </div>
                                        <h3>{{ $event['name'] ?? 'Aktivitas Pembelajaran' }}</h3>
                                        @if (! empty($event['course']['fullname']))
                                            <p>{{ $event['course']['fullname'] }}</p>
                                        @endif
                                    </div>
                                    <div class="notification-final-item-meta">
                                        <time>{{ $time ? date('d M Y, H:i', (int) $time) : 'Waktu belum tersedia' }}</time>
                                        @if ($eventUrl)
                                            <a href="{{ $eventUrl }}" aria-label="Buka {{ strtolower($eventLabel) }} {{ $event['name'] ?? '' }}">
                                                <i data-lucide="arrow-right" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="notification-final-empty">
                            <span aria-hidden="true"><i data-lucide="bell-off"></i></span>
                            <h3>Belum ada notifikasi</h3>
                            <p>
                                {{ ($activeFilter ?? 'all') === 'deadline'
                                    ? 'Belum ada batas waktu tugas atau kuis yang perlu diperhatikan.'
                                    : 'Pembaruan aktivitas pembelajaran akan tampil di sini.' }}
                            </p>
                        </div>
                    @endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
