<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kerjakan kuis pembelajaran.">
    <title>{{ $quiz['name'] ?? $module['name'] ?? 'Mengerjakan Kuis' }} - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page final-quiz-attempt-page">
    @php
        $questions = collect($attemptData['questions'] ?? [])->filter(fn ($question) => is_array($question))->values();
        $summaryQuestions = collect($attemptSummary['questions'] ?? [])->filter(fn ($question) => is_array($question))->values();
        if ($summaryQuestions->isEmpty()) {
            $summaryQuestions = $questions;
        }
        $currentPage = max(0, (int) $page);
        $totalQuestions = max($summaryQuestions->count(), $questions->count());
        $currentQuestion = $questions->first() ?? [];
        $currentNumber = $currentQuestion['number'] ?? $currentQuestion['slot'] ?? ($currentPage + 1);
        $maxScore = (float) ($quiz['grade'] ?? 100);
        $deadlineTimestamp = (int) ($attemptDeadline ?? 0);
        $remainingSeconds = $deadlineTimestamp > 0 ? max(0, $deadlineTimestamp - now()->timestamp) : null;
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $formatTimestamp = fn (int $timestamp) => $timestamp > 0
            ? \Carbon\Carbon::createFromTimestamp($timestamp)->timezone($timezone)->format('d / m / Y H:i')
            : 'Tidak dibatasi';
        $questionIsAnswered = function (array $question): bool {
            $state = strtolower((string) ($question['state'] ?? ''));
            $status = strtolower(strip_tags((string) ($question['status'] ?? '')));

            return in_array($state, ['complete', 'gradedright', 'gradedwrong', 'gradedpartial'], true)
                || str_contains($status, 'saved')
                || str_contains($status, 'tersimpan')
                || str_contains($status, 'answered')
                || str_contains($status, 'dijawab');
        };
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content final-quiz-attempt-content">
                @if ($errors->any() && empty($accessExpired))
                    <div class="quiz-page-feedback is-error" role="alert">{{ $errors->first() }}</div>
                @endif

                @if ($questions->isNotEmpty())
                    <form class="final-quiz-form" method="POST" action="{{ route('courses.modules.quiz.submit', ['courseId' => $courseId, 'moduleId' => $moduleId, 'attemptId' => $attemptId]) }}" data-final-quiz-form>
                        @csrf
                        <input type="hidden" name="quiz_payload" data-quiz-payload>
                        <input type="hidden" name="finishattempt" value="0" data-finish-attempt>
                        <input type="hidden" name="page" value="{{ $currentPage }}" data-target-page>
                        @if (! empty($previewMode))<input type="hidden" name="mode" value="preview">@endif

                        <div class="quiz-attempt-layout">
                            <div class="quiz-attempt-main">
                                <section class="quiz-attempt-metrics" aria-label="Informasi pengerjaan kuis">
                                    <span class="quiz-attempt-clock" aria-hidden="true"><i data-lucide="clock-3"></i></span>
                                    <div><strong>Waktu Tersisa</strong><span data-quiz-timer>{{ $remainingSeconds === null ? 'Tidak dibatasi' : gmdate('H:i:s', $remainingSeconds) }}</span></div>
                                    <div><strong>Soal</strong><span>{{ $currentNumber }} dari {{ max(1, $totalQuestions) }}</span></div>
                                    <div><strong>Skor Maksimal</strong><span>{{ rtrim(rtrim(number_format($maxScore, 2, ',', '.'), '0'), ',') }}</span></div>
                                </section>

                                <h1 class="quiz-current-title">Soal {{ $currentNumber }}</h1>
                                <section class="quiz-question-panel" data-current-question>
                                    @foreach ($questions as $question)
                                        <div class="quiz-moodle-question">{!! $question['html'] ?? '<p>Soal belum dapat ditampilkan.</p>' !!}</div>
                                    @endforeach
                                </section>

                                <div class="quiz-page-actions">
                                    <button class="quiz-secondary-button" type="button" data-quiz-navigate="{{ max(0, $currentPage - 1) }}" data-loading-button data-loading-tone="dark" {{ $currentPage <= 0 ? 'disabled' : '' }}><i data-lucide="arrow-left"></i> Sebelumnya</button>
                                    <button class="quiz-primary-button" type="button" data-quiz-navigate="{{ $currentPage + 1 }}" data-loading-button {{ $currentPage + 1 >= max(1, $totalQuestions) ? 'disabled' : '' }}>Selanjutnya <i data-lucide="arrow-right"></i></button>
                                </div>
                            </div>

                            <aside class="quiz-question-navigation" aria-label="Daftar soal">
                                <h2>Daftar Soal</h2>
                                <div class="quiz-navigation-legend">
                                    <span><i class="is-answered"></i>Dijawab</span>
                                    <span><i></i>Belum Dijawab</span>
                                </div>
                                <div class="quiz-question-grid">
                                    @foreach ($summaryQuestions as $question)
                                        @php
                                            $questionNumber = $question['number'] ?? $question['slot'] ?? $loop->iteration;
                                            $questionPage = isset($question['page']) ? (int) $question['page'] : ($loop->iteration - 1);
                                            $answered = $questionIsAnswered($question);
                                        @endphp
                                        <button type="button" class="{{ $answered ? 'is-answered' : '' }} {{ $questionPage === $currentPage ? 'is-current' : '' }}" data-question-nav data-question-page="{{ $questionPage }}" data-question-answered="{{ $answered ? '1' : '0' }}" data-loading-button @if (! $answered) data-loading-tone="dark" @endif>{{ $questionNumber }}</button>
                                    @endforeach
                                </div>
                                <button class="quiz-finish-button" type="button" data-quiz-finish data-loading-button>Selesai</button>
                            </aside>
                        </div>
                    </form>
                @else
                    <div class="quiz-page-feedback is-error">Soal kuis belum dapat ditampilkan. Silakan coba lagi beberapa saat.</div>
                @endif
            </div>
        </main>
    </div>

    <div class="material-modal-layer quiz-modal-layer" data-unanswered-layer hidden>
        <section class="quiz-warning-modal" role="dialog" aria-modal="true" aria-labelledby="quiz-warning-title">
            <button class="material-modal-close" type="button" data-unanswered-close aria-label="Tutup peringatan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg></button>
            <span aria-hidden="true"><i data-lucide="triangle-alert"></i></span>
            <h2 id="quiz-warning-title">Masih Ada Soal Yang Belum Dijawab</h2>
        </section>
    </div>

    @if (! empty($accessExpired))
        <div class="material-modal-layer quiz-modal-layer" data-attempt-expired-layer>
            <section class="assignment-alert-modal quiz-expired-modal" role="dialog" aria-modal="true" aria-labelledby="attempt-expired-title">
                <span class="assignment-alert-art is-deadline" aria-hidden="true"><i data-lucide="alarm-clock"></i></span>
                <h2 id="attempt-expired-title">Waktu Akses Telah Berakhir</h2>
                <p>Maaf, kamu tidak dapat menyelesaikan kuis ini karena batas waktu akses telah lewat.</p>
                <div class="assignment-deadline-panel">
                    <span><i data-lucide="clock-3"></i></span>
                    <div><small>Batas Akses</small><strong>{{ $formatTimestamp($deadlineTimestamp) }}</strong></div>
                    <span><i data-lucide="calendar-days"></i></span>
                    <div><small>Waktu Saat Ini</small><strong>{{ now()->timezone($timezone)->format('d / m / Y H:i') }}</strong></div>
                </div>
                <a class="quiz-expired-return" href="{{ route('courses.show', ['courseId' => $courseId]) }}" data-loading-button>Kembali ke Detail Mata Kuliah</a>
            </section>
        </div>
    @endif

    <script>
        (() => {
            const form = document.querySelector('[data-final-quiz-form]');
            const payloadInput = form?.querySelector('[data-quiz-payload]');
            const finishInput = form?.querySelector('[data-finish-attempt]');
            const pageInput = form?.querySelector('[data-target-page]');
            const currentQuestion = form?.querySelector('[data-current-question]');
            const navButtons = Array.from(form?.querySelectorAll('[data-question-nav]') || []);
            const warningLayer = document.querySelector('[data-unanswered-layer]');
            const expiredLayer = document.querySelector('[data-attempt-expired-layer]');
            const timer = document.querySelector('[data-quiz-timer]');
            let remainingSeconds = @json($remainingSeconds);

            const currentPageAnswered = () => {
                if (!currentQuestion) return false;
                if (currentQuestion.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked')) return true;
                if (Array.from(currentQuestion.querySelectorAll('textarea')).some((field) => field.value.trim() !== '')) return true;
                if (Array.from(currentQuestion.querySelectorAll('select')).some((field) => field.value !== '')) return true;
                return Array.from(currentQuestion.querySelectorAll('input[type="text"], input[type="number"]')).some((field) => field.value.trim() !== '');
            };

            const buildPayload = () => {
                const data = [];
                const formData = new FormData(form);
                formData.forEach((value, name) => {
                    if (name !== 'quiz_payload') data.push({ name, value: String(value) });
                });
                payloadInput.value = JSON.stringify(data);
            };

            const submitToPage = (targetPage, finish = false, trigger = null) => {
                pageInput.value = String(Math.max(0, targetPage));
                finishInput.value = finish ? '1' : '0';
                buildPayload();
                window.setButtonLoading?.(trigger);
                form.submit();
            };

            document.querySelectorAll('[data-quiz-navigate]').forEach((button) => button.addEventListener('click', () => submitToPage(Number(button.dataset.quizNavigate || 0), false, button)));
            navButtons.forEach((button) => button.addEventListener('click', () => submitToPage(Number(button.dataset.questionPage || 0), false, button)));

            document.querySelector('[data-quiz-finish]')?.addEventListener('click', (event) => {
                const currentPage = Number(pageInput.value || 0);
                const unanswered = navButtons.filter((button) => {
                    const buttonPage = Number(button.dataset.questionPage || 0);
                    if (buttonPage === currentPage && currentPageAnswered()) return false;
                    return button.dataset.questionAnswered !== '1';
                });
                if (unanswered.length > 0) {
                    warningLayer.hidden = false;
                    document.body.classList.add('material-modal-open');
                    warningLayer.querySelector('button')?.focus();
                    return;
                }
                submitToPage(currentPage, true, event.currentTarget);
            });

            document.querySelector('[data-unanswered-close]')?.addEventListener('click', () => {
                warningLayer.hidden = true;
                document.body.classList.remove('material-modal-open');
            });

            if (expiredLayer) document.body.classList.add('material-modal-open');
            if (remainingSeconds !== null && timer) {
                window.setInterval(() => {
                    remainingSeconds = Math.max(0, remainingSeconds - 1);
                    const hours = String(Math.floor(remainingSeconds / 3600)).padStart(2, '0');
                    const minutes = String(Math.floor((remainingSeconds % 3600) / 60)).padStart(2, '0');
                    const seconds = String(remainingSeconds % 60).padStart(2, '0');
                    timer.textContent = `${hours}:${minutes}:${seconds}`;
                    if (remainingSeconds === 0 && !expiredLayer) window.location.reload();
                }, 1000);
            }
        })();
    </script>
</body>
</html>
