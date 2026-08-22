<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nilai tugas dan quiz mata kuliah.">
    <title>Detail Nilai - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page grade-detail-page">
    @php
        use Illuminate\Support\Str;

        $courseName = $course['fullname'] ?? $course['displayname'] ?? 'Mata Kuliah';
        preg_match('/\b([A-Z]{2,})\b/u', $courseName, $courseAcronymMatch);
        $courseInitials = $courseAcronymMatch[1] ?? collect(preg_split('/\s+/u', trim($courseName)) ?: [])
            ->filter(fn ($word) => Str::length($word) > 1 && ! in_array(Str::lower($word), ['dan', 'atau', 'untuk', 'dengan'], true))
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
        $courseInitials = Str::substr($courseInitials, 0, 2) ?: 'MK';
        $activeTab = request()->query('tab') === 'quiz' ? 'quizzes' : 'tasks';
        $gradeGroups = [
            'tasks' => [
                'label' => 'Tugas',
                'empty' => 'Belum ada nilai tugas yang dapat ditampilkan.',
                'rows' => collect($gradeRows['tasks'] ?? [])->filter(fn ($row) => is_array($row))->values(),
            ],
            'quizzes' => [
                'label' => 'Quiz',
                'empty' => 'Belum ada nilai quiz yang dapat ditampilkan.',
                'rows' => collect($gradeRows['quizzes'] ?? [])->filter(fn ($row) => is_array($row))->values(),
            ],
        ];
        $formatGrade = function ($value): string {
            if (! is_numeric($value)) {
                return (string) $value;
            }

            $numeric = (float) $value;

            return floor($numeric) === $numeric
                ? number_format($numeric, 0, ',', '.')
                : rtrim(rtrim(number_format($numeric, 2, ',', '.'), '0'), ',');
        };
        $formatSubmitDate = fn ($timestamp) => $timestamp
            ? \Carbon\Carbon::createFromTimestamp((int) $timestamp, config('app.timezone'))->format('d/m/Y')
            : '-';
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content grade-detail-content">
                <header class="final-course-header grade-course-header">
                    <span class="final-course-header-initial" aria-hidden="true">{{ $courseInitials }}</span>
                    <h1>{{ $courseName }}</h1>
                    <a class="final-back-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-label="Kembali ke detail mata kuliah">
                        <i data-lucide="undo-2" aria-hidden="true"></i>
                    </a>
                </header>

                @if ($gradeError)
                    <div class="student-inline-error" role="alert">{{ $gradeError }}</div>
                @endif

                <section class="grade-detail-panel" aria-labelledby="grade-detail-title">
                    <h2 id="grade-detail-title">Detail Nilai</h2>

                    <div class="grade-tabs" role="tablist" aria-label="Jenis nilai">
                        @foreach ($gradeGroups as $key => $group)
                            <button
                                id="grade-tab-{{ $key }}"
                                class="grade-tab {{ $activeTab === $key ? 'is-active' : '' }}"
                                type="button"
                                role="tab"
                                aria-controls="grade-panel-{{ $key }}"
                                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                                tabindex="{{ $activeTab === $key ? '0' : '-1' }}"
                                data-grade-tab="{{ $key }}"
                            >{{ $group['label'] }}</button>
                        @endforeach
                    </div>

                    @foreach ($gradeGroups as $key => $group)
                        <div
                            id="grade-panel-{{ $key }}"
                            class="grade-tab-panel"
                            role="tabpanel"
                            aria-labelledby="grade-tab-{{ $key }}"
                            data-grade-panel="{{ $key }}"
                            @if ($activeTab !== $key) hidden @endif
                        >
                            <div class="grade-table-scroll">
                                <table class="grade-detail-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">Nama</th>
                                            <th scope="col">Tanggal Submit</th>
                                            <th scope="col">Nilai</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($group['rows'] as $row)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td><strong>{{ $row['name'] }}</strong></td>
                                                <td>{{ $formatSubmitDate($row['submitted_at'] ?? null) }}</td>
                                                <td>
                                                    @if ($row['graded'] ?? false)
                                                        <span class="grade-score-pill is-graded">
                                                            {{ $formatGrade($row['grade'] ?? $row['grade_text'] ?? '-') }}@if (is_numeric($row['max_grade'] ?? null)) / {{ $formatGrade($row['max_grade']) }}@endif
                                                        </span>
                                                    @else
                                                        <span class="grade-score-pill">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="grade-status-pill {{ ($row['graded'] ?? false) ? 'is-graded' : '' }}">
                                                        {{ ($row['graded'] ?? false) ? 'Dinilai' : 'Belum Dinilai' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="grade-empty-row">
                                                <td colspan="5">{{ $group['empty'] }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </section>
            </div>
        </main>
    </div>

    <script>
        (() => {
            const tabs = Array.from(document.querySelectorAll('[data-grade-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-grade-panel]'));

            const activate = (key, updateUrl = true) => {
                tabs.forEach((tab) => {
                    const active = tab.dataset.gradeTab === key;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                });
                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.gradePanel !== key;
                });

                if (updateUrl) {
                    const url = new URL(window.location.href);
                    key === 'quizzes' ? url.searchParams.set('tab', 'quiz') : url.searchParams.delete('tab');
                    window.history.replaceState({}, '', url);
                }
            };

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => activate(tab.dataset.gradeTab));
                tab.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                    event.preventDefault();
                    const direction = event.key === 'ArrowRight' ? 1 : -1;
                    const next = tabs[(index + direction + tabs.length) % tabs.length];
                    activate(next.dataset.gradeTab);
                    next.focus();
                });
            });
        })();
    </script>
</body>
</html>
