<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Detail mata kuliah dan aktivitas pembelajaran.">
    <title>{{ $course['fullname'] ?? 'Detail Mata Kuliah' }} - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="student-shell-page course-detail-final-page">
    @php
    use Illuminate\Support\Str;

    $courseName = $course['fullname'] ?? $course['displayname'] ?? 'Detail Mata Kuliah';
    preg_match('/\b([A-Z]{2,})\b/u', $courseName, $courseAcronymMatch);
    $courseInitials = $courseAcronymMatch[1] ?? collect(preg_split('/\s+/u', trim($courseName)) ?: [])
    ->filter(fn ($word) => Str::length($word) > 1 && ! in_array(Str::lower($word), ['dan', 'atau', 'untuk', 'dengan'], true))
    ->take(2)
    ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
    ->implode('');
    $courseInitials = Str::substr($courseInitials, 0, 2);
    $courseInitials = $courseInitials !== '' ? $courseInitials : 'MK';
    $progressPercent = max(0, min(100, (int) ($courseProgress['percent'] ?? 0)));
    $completionStatuses = collect($courseProgress['statuses'] ?? [])
    ->filter(fn ($status) => is_array($status))
    ->keyBy(fn ($status) => (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0));
    $visibleContents = collect($contents ?? [])->map(function ($section) {
    $section['modules'] = collect($section['modules'] ?? [])
    ->filter(fn ($module) => (bool) ($module['uservisible'] ?? true))
    ->values()
    ->all();

    return $section;
    })->values();
    $allModules = $visibleContents->flatMap(fn ($section) => $section['modules'] ?? [])->values();

    $moduleIsCompleted = function (array $module) use ($completionStatuses, $assignmentCompletionStatuses): bool {
    $moduleId = (int) ($module['id'] ?? 0);
    $moduleType = strtolower((string) ($module['modname'] ?? ''));

    if ($moduleType === 'assign' && array_key_exists($moduleId, $assignmentCompletionStatuses)) {
    return $assignmentCompletionStatuses[$moduleId];
    }

    $status = $completionStatuses->get($moduleId, []);
    $state = (int) ($status['state'] ?? $status['completionstate'] ?? 0);
    $moduleState = (int) ($module['completiondata']['state'] ?? 0);

    return in_array($state, [1, 2, 3], true)
    || in_array($moduleState, [1, 2, 3], true)
    || (bool) ($status['completed'] ?? false);
    };

    $activityGroups = [
    'material' => [
    'label' => 'Materi',
    'types' => ['resource', 'page', 'book', 'folder', 'url'],
    'icon' => 'material',
    ],
    'assignment' => [
    'label' => 'Tugas',
    'types' => ['assign'],
    'icon' => 'task',
    ],
    'quiz' => [
    'label' => 'Quiz',
    'types' => ['quiz'],
    'icon' => 'quiz',
    ],
    ];
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content course-detail-content">
                <header class="final-course-header">
                    <span class="final-course-header-initial" aria-hidden="true">{{ $courseInitials }}</span>
                    <h1>{{ $courseName }}</h1>
                    <a class="final-back-button" href="{{ route('dashboard') }}#courses" aria-label="Kembali ke daftar mata kuliah" data-loading-button data-loading-tone="dark">
                        <i data-lucide="undo-2" aria-hidden="true"></i>
                    </a>
                </header>

                <section class="final-course-progress-block" aria-labelledby="course-progress-title">
                    <div class="final-course-progress-heading">
                        <h2 id="course-progress-title">Progres Pembelajaran</h2>
                        <strong>{{ $progressPercent }}%</strong>
                    </div>
                    <div class="final-course-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}">
                        <span style="width: {{ $progressPercent }}%"></span>
                    </div>
                </section>

                <nav class="final-course-tabs" aria-label="Bagian mata kuliah">
                    <a class="active" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-current="page">TOPIK</a>
                    <a href="{{ route('grades', ['courseid' => $courseId]) }}">NILAI</a>
                    <a href="{{ route('courses.achievements', ['courseId' => $courseId]) }}">PENCAPAIAN</a>
                </nav>

                @if ($contentError)
                <div class="student-inline-error" role="alert">
                    Materi dan aktivitas belum dapat dimuat. Silakan coba lagi beberapa saat.
                </div>
                @endif

                <section class="final-activity-summary" aria-label="Ringkasan aktivitas">
                    @foreach ($activityGroups as $group)
                    @php
                    $groupModules = $allModules->filter(
                    fn ($module) => in_array(strtolower((string) ($module['modname'] ?? '')), $group['types'], true)
                    );
                    $groupCompleted = $groupModules->filter(fn ($module) => $moduleIsCompleted($module))->count();
                    $groupTotal = $groupModules->count();
                    @endphp
                    <article class="final-activity-summary-card">
                        <span class="final-summary-icon" aria-hidden="true">
                            <span class="asset-icon asset-icon-{{ $group['icon'] }}"></span>
                        </span>
                        <div>
                            <h2>{{ $group['label'] }}</h2>
                            <strong>{{ $groupCompleted }} / {{ $groupTotal }}</strong>
                            <span class="final-status final-status-success">Selesai</span>
                        </div>
                    </article>
                    @endforeach
                </section>

                @if ($visibleContents->isNotEmpty())
                <section class="final-topic-list" aria-label="Topik pembelajaran">
                    @foreach ($visibleContents as $section)
                    @php
                    $sectionModules = is_array($section['modules'] ?? null) ? $section['modules'] : [];
                    $sectionName = trim((string) ($section['name'] ?? ''));
                    $sectionName = $sectionName !== '' ? $sectionName : 'Topik '.($loop->iteration);
                    @endphp
                    <details class="final-topic" @if ($loop->first) open @endif>
                        <summary>
                            <span class="final-topic-name">{{ $sectionName }}</span>
                            <span class="final-topic-meta">
                                <span>{{ count($sectionModules) }} Aktivitas</span>
                                <i data-lucide="chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>

                        @if ($sectionModules !== [])
                        <div class="final-topic-activities">
                            @foreach ($sectionModules as $module)
                            @php
                            $moduleType = strtolower((string) ($module['modname'] ?? ''));
                            $moduleName = trim((string) ($module['name'] ?? 'Aktivitas Pembelajaran'));
                            $isDiscussion = in_array($moduleType, ['forum', 'chat'], true)
                            || preg_match('/\b(announcement|announcements|pengumuman|diskusi)\b/iu', $moduleName) === 1;
                            $completed = $moduleIsCompleted($module);
                            $category = match ($moduleType) {
                            'assign' => 'task',
                            'quiz' => 'quiz',
                            default => 'material',
                            };
                            $statusLabel = $isDiscussion ? null : match ($moduleType) {
                            'assign' => $completed ? 'Sudah Dikumpulkan' : 'Belum Dikumpulkan',
                            'quiz' => $completed ? 'Sudah Dikerjakan' : 'Belum Dikerjakan',
                            default => $completed ? 'Sudah Diselesaikan' : 'Belum Diselesaikan',
                            };
                            @endphp
                            <a class="final-activity-row" href="{{ route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => (int) ($module['id'] ?? 0)]) }}" data-loading-button data-loading-mode="row" data-loading-tone="dark">
                                <span class="final-row-icon" aria-hidden="true">
                                    @if ($isDiscussion)
                                    <i data-lucide="messages-square"></i>
                                    @else
                                    <span class="asset-icon asset-icon-{{ $category }}"></span>
                                    @endif
                                </span>
                                <strong>{{ $moduleName }}</strong>
                                @if ($statusLabel !== null)
                                <span class="final-status {{ $completed ? 'final-status-success' : 'final-status-warning' }}">
                                    {{ $statusLabel }}
                                </span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                        @else
                        <p class="final-topic-empty">Belum ada aktivitas pada topik ini.</p>
                        @endif
                    </details>
                    @endforeach
                </section>
                @else
                <section class="student-empty-state">
                    <i data-lucide="book-open" aria-hidden="true"></i>
                    <h2>Belum ada aktivitas</h2>
                    <p>Aktivitas pembelajaran akan tampil di halaman ini.</p>
                </section>
                @endif
            </div>
        </main>
    </div>
</body>

</html>
