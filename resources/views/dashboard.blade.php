<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard pembelajaran mahasiswa E-Learning Lite.">
    <title>Dashboard E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page dashboard-final-page">
    @php
        use Illuminate\Support\Str;

        $displayName = $user->name ?? session('username') ?? 'Mahasiswa';
        $courses = is_array($moodleCourses ?? null) ? $moodleCourses : [];
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'dashboard'])

        <main class="student-main">
            @include('partials.student_topbar', ['unreadNotificationCount' => $unreadNotificationCount ?? 0])

            <div class="student-page-content dashboard-content">
                <section class="final-dashboard-hero">
                    <div class="final-dashboard-hero-copy">
                        <span class="final-dashboard-label">Dashboard E-Learning Lite</span>
                        <h1>Selamat datang, {{ $displayName }}!</h1>
                        <p>Pantau perkembangan pembelajaranmu dan aktivitas yang perlu diselesaikan.</p>
                    </div>
                    <div class="final-dashboard-illustration" role="img" aria-label="Ilustrasi mahasiswa sedang belajar"></div>
                </section>

                @if (! empty($notificationSummaryError))
                    <div class="student-inline-error" role="alert">
                        Ringkasan aktivitas belum dapat diperbarui. Silakan coba lagi beberapa saat.
                    </div>
                @endif

                @if ($courses !== [])
        <section id="courses" class="final-course-grid" aria-label="Daftar mata kuliah">
                        @foreach ($courses as $course)
                            @php
                                $courseId = (int) ($course['id'] ?? 0);
                                $courseName = $course['fullname'] ?? $course['displayname'] ?? 'Mata kuliah';
                                preg_match('/\b([A-Z]{2,})\b/u', $courseName, $courseAcronymMatch);
                                $courseInitials = $courseAcronymMatch[1] ?? collect(preg_split('/\s+/u', trim($courseName)) ?: [])
                                    ->filter(fn ($word) => Str::length($word) > 1 && ! in_array(Str::lower($word), ['dan', 'atau', 'untuk', 'dengan'], true))
                                    ->take(2)
                                    ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
                                    ->implode('');
                                $courseInitials = Str::substr($courseInitials, 0, 2);
                                $courseInitials = $courseInitials !== '' ? $courseInitials : 'MK';
                                $progressPercent = $course['progress']['percent'] ?? 0;
                                $progressPercent = max(0, min(100, (int) $progressPercent));
                                $courseDeadlines = is_array($courseDeadlineEvents[$courseId] ?? null)
                                    ? $courseDeadlineEvents[$courseId]
                                    : [];
                                $visibleDeadlines = array_slice($courseDeadlines, 0, 3);
                            @endphp

                            <article class="final-course-card">
                                <header class="final-course-card-header">
                                    <span class="final-course-initial" aria-hidden="true">{{ $courseInitials }}</span>
                                    <h2>{{ $courseName }}</h2>
                                </header>

                                <div class="final-course-progress" aria-label="Progres pembelajaran {{ $progressPercent }} persen">
                                    <span style="width: {{ $progressPercent }}%"></span>
                                </div>

                                <section class="final-deadline-panel" aria-label="Deadline {{ $courseName }}">
                                    <header>
                                        <span class="final-deadline-title">
                                            <i data-lucide="calendar-days" aria-hidden="true"></i>
                                            Deadline
                                        </span>
                                        <span class="final-deadline-count">{{ count($courseDeadlines) }}</span>
                                    </header>

                                    @if ($visibleDeadlines !== [])
                                        <div class="final-deadline-list">
                                            @foreach ($visibleDeadlines as $deadlineEvent)
                                                @php
                                                    $deadlineTime = (int) ($deadlineEvent['timesort'] ?? $deadlineEvent['timestart'] ?? 0);
                                                    $moduleId = (int) ($deadlineEvent['cmid'] ?? 0);
                                                    $moduleType = strtolower((string) ($deadlineEvent['modulename'] ?? ''));
                                                    $deadlineIcon = $moduleType === 'quiz' ? 'clock' : 'file';
                                                    $deadlineName = $deadlineEvent['name'] ?? ($moduleType === 'quiz' ? 'Quiz' : 'Tugas');
                                                    $deadlineHref = $moduleId > 0
                                                        ? route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId])
                                                        : route('notifications', ['filter' => 'deadline']);
                                                @endphp
                                                <a class="final-deadline-item" href="{{ $deadlineHref }}">
                                                    <span class="asset-icon asset-icon-{{ $deadlineIcon }}" aria-hidden="true"></span>
                                                    <span class="final-deadline-name">{{ $deadlineName }}</span>
                                                    <time datetime="{{ $deadlineTime > 0 ? date('c', $deadlineTime) : '' }}">
                                                        {{ $deadlineTime > 0 ? date('d / m / Y', $deadlineTime) : 'Belum tersedia' }}
                                                    </time>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="final-deadline-empty">Belum ada deadline terdekat.</p>
                                    @endif
                                </section>

                                <a class="final-course-action" href="{{ route('courses.show', ['courseId' => $courseId]) }}">
                                    <span class="asset-icon asset-icon-book" aria-hidden="true"></span>
                                    Materi
                                </a>
                            </article>
                        @endforeach
                    </section>
                @else
                    <section class="student-empty-state">
                        <i data-lucide="book-open" aria-hidden="true"></i>
                        <h2>Belum ada mata kuliah</h2>
                        <p>Mata kuliah yang kamu ikuti akan tampil di halaman ini.</p>
                    </section>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
