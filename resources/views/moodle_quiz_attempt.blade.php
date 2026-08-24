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
        $currentSummaryIndex = $summaryQuestions->search(function (array $question) use ($currentPage, $currentNumber): bool {
            if (isset($question['page'])) {
                return (int) $question['page'] === $currentPage;
            }

            return (string) ($question['number'] ?? $question['slot'] ?? '') === (string) $currentNumber;
        });
        $currentSummaryIndex = $currentSummaryIndex === false ? max(0, min($currentPage, $summaryQuestions->count() - 1)) : (int) $currentSummaryIndex;
        $isFirstQuestion = $currentSummaryIndex <= 0;
        $isLastQuestion = $currentSummaryIndex >= max(0, $summaryQuestions->count() - 1);
        $previousQuestion = $summaryQuestions->get(max(0, $currentSummaryIndex - 1), []);
        $nextQuestion = $summaryQuestions->get(min(max(0, $summaryQuestions->count() - 1), $currentSummaryIndex + 1), []);
        $previousQuestionPage = isset($previousQuestion['page']) ? (int) $previousQuestion['page'] : max(0, $currentPage - 1);
        $nextQuestionPage = isset($nextQuestion['page']) ? (int) $nextQuestion['page'] : ($currentPage + 1);
        $maxScore = (float) ($quiz['grade'] ?? 100);
        $deadlineTimestamp = (int) ($attemptDeadline ?? 0);
        $remainingSeconds = $deadlineTimestamp > 0 ? max(0, $deadlineTimestamp - now()->timestamp) : null;
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $formatTimestamp = fn (int $timestamp) => $timestamp > 0
            ? \Carbon\Carbon::createFromTimestamp($timestamp)->timezone($timezone)->format('d / m / Y H:i')
            : 'Tidak dibatasi';
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle', 'hideMobileToggle' => true])

        <main class="student-main">
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

                                <div class="quiz-page-actions {{ $isLastQuestion ? 'is-last-question' : '' }}">
                                    <button class="quiz-secondary-button" type="button" data-quiz-navigate="{{ $previousQuestionPage }}" data-loading-button aria-label="Buka soal sebelumnya" {{ $isFirstQuestion ? 'disabled' : '' }}><i data-lucide="arrow-left"></i><span>Sebelumnya</span></button>
                                    <button class="quiz-primary-button quiz-next-button" type="button" data-quiz-navigate="{{ $nextQuestionPage }}" data-loading-button aria-label="Buka soal berikutnya" {{ $isLastQuestion ? 'disabled' : '' }}><span>Selanjutnya</span><i data-lucide="arrow-right"></i></button>
                                    @if ($isLastQuestion)
                                        <button class="quiz-finish-button quiz-mobile-finish" type="button" data-quiz-finish data-loading-button>Selesai</button>
                                    @endif
                                </div>
                            </div>

                            <aside class="quiz-question-navigation" aria-label="Daftar soal">
                                <h2>Daftar Soal</h2>
                                <div class="quiz-question-grid">
                                    @foreach ($summaryQuestions as $question)
                                        @php
                                            $questionNumber = $question['number'] ?? $question['slot'] ?? $loop->iteration;
                                            $questionPage = isset($question['page']) ? (int) $question['page'] : ($loop->iteration - 1);
                                        @endphp
                                        <button type="button" class="{{ $questionPage === $currentPage ? 'is-current' : '' }}" data-question-nav data-question-page="{{ $questionPage }}" data-question-answered="0" data-loading-button>{{ $questionNumber }}</button>
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
            const answerStorageKey = `e-learning-lite:quiz-attempt:${@json($attemptId)}:answered-pages`;

            const readAnsweredPages = () => {
                try {
                    const storedPages = JSON.parse(window.sessionStorage.getItem(answerStorageKey) || '[]');
                    return new Set(Array.isArray(storedPages) ? storedPages.map(Number) : []);
                } catch (_) {
                    return new Set();
                }
            };

            const answeredPages = readAnsweredPages();

            const persistAnsweredPages = () => {
                try {
                    window.sessionStorage.setItem(answerStorageKey, JSON.stringify(Array.from(answeredPages)));
                } catch (_) {
                    // The visual state remains functional when storage is unavailable.
                }
            };

            const renderQuestionState = (button, answered) => {
                button.dataset.questionAnswered = answered ? '1' : '0';
            };

            navButtons.forEach((button) => {
                renderQuestionState(button, answeredPages.has(Number(button.dataset.questionPage || 0)));
            });

            const removeMoodleTextLabel = (root, label) => {
                if (!root) return;

                const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
                const matchingNodes = [];
                while (walker.nextNode()) {
                    if (walker.currentNode.nodeValue.trim().toLocaleLowerCase('id') === label.toLocaleLowerCase('id')) {
                        matchingNodes.push(walker.currentNode);
                    }
                }

                matchingNodes.forEach((node) => {
                    const parent = node.parentElement;
                    node.remove();
                    if (parent && parent !== root && parent.textContent.trim() === '' && parent.children.length === 0) {
                        parent.remove();
                    }
                });
            };

            removeMoodleTextLabel(currentQuestion, 'Teks soal');

            const currentPageAnswered = () => {
                if (!currentQuestion) return false;
                if (Array.from(currentQuestion.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked')).some((field) => field.value !== '-1' && !field.closest('.qtype_multichoice_clearchoice'))) return true;
                if (Array.from(currentQuestion.querySelectorAll('textarea')).some((field) => field.value.trim() !== '')) return true;
                if (Array.from(currentQuestion.querySelectorAll('select')).some((field) => field.value !== '' && field.value !== '-1')) return true;
                return Array.from(currentQuestion.querySelectorAll('input[type="text"], input[type="number"]')).some((field) => field.value.trim() !== '');
            };

            const syncCurrentQuestionState = () => {
                const activePage = Number(pageInput?.value || 0);
                const currentNavButton = navButtons.find((button) => Number(button.dataset.questionPage || 0) === activePage);
                if (!currentNavButton) return;

                const answered = currentPageAnswered();
                if (answered) {
                    answeredPages.add(activePage);
                } else {
                    answeredPages.delete(activePage);
                }
                renderQuestionState(currentNavButton, answered);
                persistAnsweredPages();
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
            currentQuestion?.addEventListener('change', syncCurrentQuestionState);
            currentQuestion?.addEventListener('input', syncCurrentQuestionState);
            currentQuestion?.addEventListener('click', (event) => {
                const optionRow = event.target.closest('.answer > div');
                if (!optionRow || optionRow.classList.contains('qtype_multichoice_clearchoice')) return;
                if (event.target.closest('input, label, a, button')) return;

                optionRow.querySelector('input[type="radio"], input[type="checkbox"]')?.click();
            });
            syncCurrentQuestionState();

            document.querySelectorAll('[data-quiz-finish]').forEach((button) => button.addEventListener('click', (event) => {
                    const currentPage = Number(pageInput.value || 0);
                    const unanswered = navButtons.filter((navButton) => {
                        const buttonPage = Number(navButton.dataset.questionPage || 0);
                        if (buttonPage === currentPage && currentPageAnswered()) return false;
                        return navButton.dataset.questionAnswered !== '1';
                    });
                    if (unanswered.length > 0) {
                        warningLayer.hidden = false;
                        document.body.classList.add('material-modal-open');
                        warningLayer.querySelector('button')?.focus();
                        return;
                    }
                    submitToPage(currentPage, true, event.currentTarget);
                }));

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
