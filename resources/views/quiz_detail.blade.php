<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Lihat dan kerjakan kuis pembelajaran.">
    <title>{{ $quiz['name'] ?? $module['name'] ?? 'Detail Kuis' }} - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="student-shell-page final-quiz-page">
    @php
    $quizName = $quiz['name'] ?? $module['name'] ?? 'Detail Kuis';
    $description = trim(strip_tags((string) ($quiz['intro'] ?? $module['description'] ?? '')));
    $description = $description !== '' ? $description : 'Kerjakan kuis berikut untuk menguji pemahaman kamu.';
    $timezone = config('app.timezone', 'Asia/Jakarta');
    $formatTimestamp = fn (int $timestamp) => $timestamp > 0
    ? \Carbon\Carbon::createFromTimestamp($timestamp)->timezone($timezone)->format('d / m / Y H:i')
    : 'Tidak dibatasi';
    $timeOpen = (int) ($quiz['timeopen'] ?? 0);
    $timeClose = (int) ($quiz['timeclose'] ?? 0);
    $timeLimit = (int) ($quiz['timelimit'] ?? 0);
    $completionStatuses = collect($courseProgress['statuses'] ?? [])
    ->filter(fn ($status) => is_array($status))
    ->keyBy(fn ($status) => (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0));
    $quizCompletion = $completionStatuses->get((int) $moduleId, []);
    $completionState = (int) ($quizCompletion['state'] ?? $quizCompletion['completionstate'] ?? $module['completiondata']['state'] ?? 0);
    $hasFinishedAttempt = collect($quizAttempts ?? [])->contains(fn ($attempt) => strtolower((string) ($attempt['state'] ?? '')) === 'finished');
    $quizIsCompleted = in_array($completionState, [1, 2, 3], true)
    || (bool) ($quizCompletion['completed'] ?? false)
    || $hasFinishedAttempt;
    $accessNotStarted = $timeOpen > 0 && now()->timestamp < $timeOpen;
        $accessEnded=$timeClose> 0 && now()->timestamp > $timeClose;
        $showExpired = $accessEnded || (bool) session('quiz_access_expired');
        $expiredAt = (int) session('quiz_expired_at', $timeClose);
        $questionCountValue = $quiz['questioncount'] ?? null;
        $questionCount = is_numeric($questionCountValue) ? (int) $questionCountValue : 0;
        $completionFeedback = session('quiz_completion_feedback');
        $pointsAwarded = (int) ($completionFeedback['points_awarded'] ?? 0);
        $earnedBadge = is_array($completionFeedback['badge'] ?? null) ? $completionFeedback['badge'] : null;
        $score = $completionFeedback['score'] ?? null;
        $maxScore = (float) ($completionFeedback['max_score'] ?? $quiz['grade'] ?? 100);
        $formatScore = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $badgeSlug = in_array($earnedBadge['slug'] ?? '', ['knowledge', 'goal', 'perfection'], true)
        ? $earnedBadge['slug']
        : 'knowledge';
        @endphp

        <div class="student-shell">
            @include('partials.sidebar', ['activeNav' => 'moodle'])

            <main class="student-main">
                @include('partials.student_topbar')

                <div class="student-page-content final-quiz-content">
                    <header class="final-quiz-header">
                        <span class="final-quiz-title-icon" aria-hidden="true"><span class="asset-icon asset-icon-quiz"></span></span>
                        <h1>{{ $quizName }}</h1>
                        <span class="final-quiz-status {{ $quizIsCompleted ? 'is-completed' : 'is-pending' }}">
                            {{ $quizIsCompleted ? 'Sudah Dikerjakan' : 'Belum Dikerjakan' }}
                        </span>
                        <a class="final-back-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-label="Kembali ke detail mata kuliah"><i data-lucide="undo-2"></i></a>
                    </header>

                    @if (($errors->any() && ! $showExpired) || $quizError)
                    <div class="quiz-page-feedback is-error" role="alert">{{ $errors->first() ?: 'Informasi kuis belum dapat ditampilkan. Silakan coba lagi.' }}</div>
                    @endif

                    <section class="quiz-access-card" aria-label="Jadwal akses kuis">
                        <span class="quiz-access-icon" aria-hidden="true"><i data-lucide="clock-3"></i></span>
                        <div><span>Waktu Akses</span><strong>{{ $formatTimestamp($timeOpen) }}</strong></div>
                        <div><span>Batas Akses</span><strong>{{ $formatTimestamp($timeClose) }}</strong></div>
                    </section>

                    <section class="quiz-information-card" aria-labelledby="quiz-description-title">
                        <dl>
                            <dt>Jenis Quiz</dt>
                            <dd>Pilihan Jawaban</dd>
                            <dt>Jumlah Soal</dt>
                            <dd>{{ $questionCount > 0 ? $questionCount : 'Tersedia saat kuis dimulai' }}</dd>
                            <dt>Durasi</dt>
                            <dd>{{ $timeLimit > 0 ? max(1, (int) ceil($timeLimit / 60)).' Menit' : 'Tidak dibatasi' }}</dd>
                            <dt>Poin</dt>
                            <dd>10 Poin</dd>
                        </dl>
                        <h2 id="quiz-description-title">Deskripsi</h2>
                        <p>{{ $description }}</p>
                    </section>

                    <div class="quiz-detail-actions">
                        @if (! $quizIsCompleted && ! $accessEnded)
                        <form method="POST" action="{{ route('courses.modules.quiz.start', ['courseId' => $courseId, 'moduleId' => $moduleId]) }}">
                            @csrf
                            <button class="quiz-primary-button" type="submit" {{ $accessNotStarted ? 'disabled' : '' }}>Kerjakan Kuis</button>
                        </form>
                        @endif
                    </div>
                </div>
            </main>
        </div>

        @if ($showExpired)
        <div class="material-modal-layer quiz-modal-layer" data-quiz-expired-layer>
            <section class="assignment-alert-modal quiz-expired-modal" role="dialog" aria-modal="true" aria-labelledby="quiz-expired-title">
                <button class="material-modal-close" type="button" data-quiz-expired-close aria-label="Tutup pemberitahuan batas akses"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="m15 9-6 6"></path>
                        <path d="m9 9 6 6"></path>
                    </svg></button>
                <span class="assignment-alert-art is-deadline" aria-hidden="true"><i data-lucide="alarm-clock"></i></span>
                <h2 id="quiz-expired-title">Waktu Akses Telah Berakhir</h2>
                <p>Maaf, kamu tidak dapat mengerjakan kuis ini karena batas waktu akses telah lewat.</p>
                <div class="assignment-deadline-panel">
                    <span><i data-lucide="clock-3"></i></span>
                    <div><small>Batas Akses</small><strong>{{ $formatTimestamp($expiredAt) }}</strong></div>
                    <span><i data-lucide="calendar-days"></i></span>
                    <div><small>Waktu Saat Ini</small><strong>{{ now()->timezone($timezone)->format('d / m / Y H:i') }}</strong></div>
                </div>
            </section>
        </div>
        @endif

        @if (is_array($completionFeedback))
        <div class="material-modal-layer quiz-result-layer" data-quiz-result-layer>
            <section class="quiz-result-modal" role="dialog" aria-modal="true" aria-labelledby="quiz-result-title">
                <span class="quiz-result-check" aria-hidden="true"><i data-lucide="circle-check-big"></i></span>
                <h2 id="quiz-result-title">Kuis Berhasil Diselesaikan</h2>
                <p>Terima kasih telah mengerjakan<br>kuis ini dengan baik</p>
                <div class="quiz-result-summary">
                    <span class="quiz-result-score-icon" aria-hidden="true"><i data-lucide="notebook-tabs"></i></span>
                    <div><small>Skor Anda</small><strong>{{ $formatScore($score) }} / {{ $formatScore($maxScore) }}</strong></div>
                    <span class="material-points-star" aria-hidden="true"><i data-lucide="star"></i></span>
                    <div><small>Poin Diperoleh</small><strong>+ {{ $pointsAwarded }} Poin</strong></div>
                </div>
                <a class="quiz-result-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" data-quiz-result-continue>Kembali ke Detail Mata Kuliah</a>
            </section>
        </div>
        @endif

        @if ($earnedBadge)
        <div class="material-modal-layer material-feedback-layer" data-quiz-badge-layer hidden>
            <section class="material-feedback-modal material-badge-modal" role="dialog" aria-modal="true" aria-labelledby="quiz-badge-title">
                <button class="material-modal-close" type="button" data-quiz-badge-close aria-label="Tutup pemberitahuan badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="m15 9-6 6"></path>
                        <path d="m9 9 6 6"></path>
                    </svg></button>
                <div class="material-badge-art material-badge-art--{{ $badgeSlug }}" aria-label="Badge {{ $earnedBadge['name'] }}"></div>
                <h2 id="quiz-badge-title">Badge Baru Diperoleh</h2>
                <p>Selamat kamu telah memperoleh<br>badge baru atas pencapaianmu</p>
                <div class="material-badge-card">
                    <div class="material-badge-mini material-badge-art--{{ $badgeSlug }}" aria-hidden="true"></div>
                    <div><strong>{{ $earnedBadge['name'] }}</strong><span>{{ $earnedBadge['description'] }}</span></div>
                </div>
            </section>
        </div>
        @endif

        <script>
            (() => {
                const expiredLayer = document.querySelector('[data-quiz-expired-layer]');
                const resultLayer = document.querySelector('[data-quiz-result-layer]');
                const badgeLayer = document.querySelector('[data-quiz-badge-layer]');
                if (expiredLayer || resultLayer) document.body.classList.add('material-modal-open');

                document.querySelector('[data-quiz-expired-close]')?.addEventListener('click', () => {
                    expiredLayer.hidden = true;
                    document.body.classList.remove('material-modal-open');
                });

                document.querySelector('[data-quiz-result-continue]')?.addEventListener('click', (event) => {
                    if (!badgeLayer) return;
                    event.preventDefault();
                    resultLayer.hidden = true;
                    badgeLayer.hidden = false;
                    badgeLayer.querySelector('button')?.focus();
                });

                document.querySelector('[data-quiz-badge-close]')?.addEventListener('click', () => {
                    window.location.href = @json(route('courses.show', ['courseId' => $courseId]));
                });
            })();
        </script>
</body>

</html>