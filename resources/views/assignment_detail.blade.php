<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kerjakan dan kumpulkan tugas pembelajaran.">
    <title>{{ $module['name'] ?? 'Detail Tugas' }} - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page final-assignment-page">
    @php
        $taskName = $module['name'] ?? $assignment['name'] ?? 'Detail Tugas';
        $taskDescription = trim((string) ($assignmentDescription ?? ''));
        $activityInstructions = trim((string) ($assignmentInstructions ?? ''));
        $settings = $assignmentSettings ?? [
            'file_enabled' => true,
            'text_enabled' => true,
            'max_files' => 1,
            'max_file_size' => 10 * 1024 * 1024,
            'max_file_size_label' => '10 MB',
            'accepted_extensions' => ['.pdf', '.docx'],
            'accepted_types_label' => '.pdf .docx',
            'accept_attribute' => '.pdf,.docx',
            'submission_files' => [],
            'is_submitted' => false,
        ];
        $isSubmitted = (bool) ($settings['is_submitted'] ?? false);
        $submissionFiles = collect($settings['submission_files'] ?? [])->filter(fn ($file) => is_array($file))->values();
        $attachmentFiles = collect($assignmentAttachments ?? [])->filter(fn ($file) => is_array($file))->values();
        $deadlineTimestamp = (int) ($assignment['cutoffdate'] ?? 0) > 0
            ? (int) $assignment['cutoffdate']
            : (int) ($assignment['duedate'] ?? 0);
        $availableTimestamp = (int) ($assignment['allowsubmissionsfromdate'] ?? 0);
        $deadlinePassed = ! $isSubmitted && $deadlineTimestamp > 0 && now()->timestamp > $deadlineTimestamp;
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $formatTimestamp = fn (int $timestamp) => $timestamp > 0
            ? \Carbon\Carbon::createFromTimestamp($timestamp)->timezone($timezone)->format('d / m / Y H:i')
            : 'Tidak dibatasi';
        $requestedMode = request()->query('mode', 'detail');
        $pageMode = in_array($requestedMode, ['detail', 'work', 'confirm'], true) ? $requestedMode : 'detail';
        $allowedExtensions = collect($settings['accepted_extensions'] ?? [])->map(fn ($extension) => strtolower((string) $extension))->values();
        $allowedTypesLabel = $allowedExtensions->isNotEmpty()
            ? $allowedExtensions->map(fn ($extension) => strtoupper(ltrim($extension, '.')))->implode(', ')
            : 'Format yang diizinkan';
        $answerMaxBytes = (int) ($settings['max_file_size'] ?? 10 * 1024 * 1024);
        $answerMaxLabel = $settings['max_file_size_label'] ?? '10 MB';
        $completionFeedback = session('assignment_completion_feedback');
        $pointsAwarded = (int) ($completionFeedback['points_awarded'] ?? 0);
        $earnedBadge = is_array($completionFeedback['badge'] ?? null) ? $completionFeedback['badge'] : null;
        $badgeSlug = in_array($earnedBadge['slug'] ?? '', ['knowledge', 'goal', 'perfection'], true)
            ? $earnedBadge['slug']
            : 'knowledge';
        $showFormatError = $errors->has('answer_files');
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content final-assignment-content">
                <header class="final-assignment-header">
                    <span class="final-assignment-title-icon" aria-hidden="true">
                        <span class="asset-icon asset-icon-task"></span>
                    </span>
                    <div class="final-activity-heading">
                        <h1>{{ $taskName }}</h1>

                        @if ($pageMode === 'detail')
                            <span class="final-assignment-status {{ $isSubmitted ? 'is-completed' : 'is-pending' }}">
                                {{ $isSubmitted ? 'Sudah Dikumpulkan' : 'Belum Dikumpulkan' }}
                            </span>
                        @endif
                    </div>

                    @if ($pageMode === 'detail')
                        <a class="final-back-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-label="Kembali ke detail mata kuliah" data-loading-button data-loading-tone="dark">
                            <i data-lucide="undo-2" aria-hidden="true"></i>
                        </a>
                    @endif
                </header>

                @if ($contentError || $assignmentSubmissionError)
                    <div class="assignment-page-feedback is-error" role="alert">Detail tugas belum dapat ditampilkan. Silakan coba lagi beberapa saat.</div>
                @endif

                @if ($errors->any() && ! $showFormatError && ! $deadlinePassed)
                    <div class="assignment-page-feedback is-error" role="alert">{{ $errors->first() }}</div>
                @endif

                @if ($pageMode === 'detail')
                    <section class="assignment-time-card" aria-label="Jadwal pengumpulan tugas">
                        <span class="assignment-time-icon" aria-hidden="true"><i data-lucide="clock-3"></i></span>
                        <div>
                            <span>Waktu Pengumpulan</span>
                            <strong>{{ $formatTimestamp($availableTimestamp) }}</strong>
                        </div>
                        <div>
                            <span>Batas Pengumpulan</span>
                            <strong>{{ $formatTimestamp($deadlineTimestamp) }}</strong>
                        </div>
                    </section>

                    <section class="assignment-copy-section" aria-labelledby="assignment-description-title">
                        <h2 id="assignment-description-title">Deskripsi Tugas</h2>
                        @if ($taskDescription !== '')
                            <p class="assignment-activity-instructions">{{ $taskDescription }}</p>
                        @else
                            <p>Deskripsi tugas belum tersedia.</p>
                        @endif
                    </section>

                    <section class="assignment-copy-section" aria-labelledby="assignment-instructions-title">
                        <h2 id="assignment-instructions-title">Instruksi Tugas</h2>
                        @if ($activityInstructions !== '')
                            <p class="assignment-activity-instructions">{{ $activityInstructions }}</p>
                        @endif
                        <ul class="assignment-guidance-list {{ $activityInstructions !== '' ? 'has-source-instructions' : '' }}">
                            <li>Baca deskripsi tugas dengan teliti.</li>
                            <li>Kerjakan sesuai format yang telah ditentukan.</li>
                            <li>Pastikan semua bagian tugas terjawab.</li>
                            @if ($settings['file_enabled'] ?? true)
                                <li>Unggah jawaban dalam format yang sesuai.</li>
                            @endif
                            <li>Pastikan file dapat dibuka dan tidak rusak.</li>
                        </ul>
                    </section>

                    <section class="assignment-copy-section" aria-labelledby="assignment-attachments-title">
                        <h2 id="assignment-attachments-title">Lampiran</h2>
                        @if ($attachmentFiles->isNotEmpty())
                            <div class="assignment-attachment-list">
                                @foreach ($attachmentFiles as $content)
                                    @php
                                        $filename = $content['filename'] ?? 'Lampiran tugas';
                                        $fileUrl = $content['fileurl'] ?? null;
                                        if ($fileUrl && $moodleToken) {
                                            $fileUrl .= str_contains($fileUrl, '?') ? '&token='.urlencode($moodleToken) : '?token='.urlencode($moodleToken);
                                        }
                                        $fileSize = (int) ($content['filesize'] ?? 0);
                                        $fileSizeLabel = $fileSize >= 1048576
                                            ? number_format($fileSize / 1048576, 2).' MB'
                                            : ($fileSize > 0 ? number_format($fileSize / 1024, 1).' KB' : 'Ukuran tidak tersedia');
                                    @endphp
                                    <article class="assignment-attachment-row">
                                        <span class="assignment-file-icon" aria-hidden="true"><i data-lucide="file-text"></i></span>
                                        <span><strong>{{ $filename }}</strong><small>{{ $fileSizeLabel }}</small></span>
                                        @if ($fileUrl)
                                            <a href="{{ route('moodle.file.download', ['url' => $fileUrl, 'filename' => $filename]) }}" aria-label="Unduh {{ $filename }}"><i data-lucide="download"></i></a>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="assignment-empty">Tidak ada lampiran tambahan untuk tugas ini.</div>
                        @endif
                    </section>

                    <div class="assignment-detail-actions">
                        @if ($isSubmitted)
                            <a class="assignment-primary-button" href="{{ route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId, 'mode' => 'confirm']) }}" data-loading-button>Lihat Pengumpulan</a>
                        @elseif (! $deadlinePassed)
                            <a class="assignment-primary-button assignment-start-button" href="{{ route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId, 'mode' => 'work']) }}" data-loading-button>Kerjakan Tugas</a>
                        @endif
                    </div>
                @elseif ($pageMode === 'work')
                    @if (! $isSubmitted)
                        <form
                            class="assignment-work-form"
                            method="POST"
                            action="{{ route('courses.modules.assignment.submit', ['courseId' => $courseId, 'moduleId' => $moduleId, 'mode' => 'work']) }}"
                            enctype="multipart/form-data"
                            data-assignment-upload-form
                        >
                            @csrf
                            <input type="hidden" name="replace_files" value="1">

                            @if ($settings['file_enabled'] ?? true)
                                <h2>Unggah Tugas</h2>
                                <label class="assignment-dropzone" data-assignment-dropzone>
                                    <input
                                        type="file"
                                        name="answer_files[]"
                                        accept="{{ $settings['accept_attribute'] ?? '' }}"
                                        {{ (int) ($settings['max_files'] ?? 1) > 1 ? 'multiple' : '' }}
                                        data-assignment-file-input
                                    >
                                    <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                    <span>Tarik dan lepas file di sini atau <strong>Klik untuk memilih file</strong></span>
                                    <small>Format yang didukung: {{ $allowedTypesLabel }} (Maks {{ $answerMaxLabel }})</small>
                                </label>

                                <div class="assignment-selected-section">
                                    <h3>File yang dipilih</h3>
                                    <div class="assignment-selected-files" data-selected-files>
                                        @foreach ($submissionFiles as $file)
                                            <div class="assignment-selected-file is-existing">
                                                <span class="assignment-file-icon"><i data-lucide="file-text"></i></span>
                                                <span><strong>{{ $file['filename'] ?? 'Jawaban tugas' }}</strong><small>File jawaban tersimpan</small></span>
                                                <span class="assignment-file-ready"><i data-lucide="circle-check"></i></span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($settings['text_enabled'] ?? true)
                                <label class="assignment-note-field">
                                    <span>Catatan (Opsional)</span>
                                    <textarea name="answer" rows="6">{{ old('answer', $assignmentAnswer ?? '') }}</textarea>
                                </label>
                            @endif

                            <div class="assignment-work-actions">
                                <a class="assignment-secondary-button" href="{{ route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId]) }}" data-loading-button data-loading-tone="dark">Batal</a>
                                <button class="assignment-primary-button" type="submit" data-loading-button>Lanjutkan</button>
                            </div>
                        </form>
                    @else
                        <div class="assignment-page-feedback">Tugas ini sudah dikumpulkan.</div>
                    @endif
                @else
                    <div class="assignment-confirmation-shell">
                        <section class="assignment-confirmation" aria-labelledby="assignment-confirmation-title">
                            <h2 id="assignment-confirmation-title">Konfirmasi Pengumpulan</h2>
                            <dl class="assignment-confirmation-card">
                                <dt>Tugas</dt>
                                <dd>{{ $taskName }}</dd>
                                <dt>Batas Pengumpulan</dt>
                                <dd>{{ $formatTimestamp($deadlineTimestamp) }}</dd>
                                <dt>File yang diunggah</dt>
                                <dd>
                                    @forelse ($submissionFiles as $file)
                                        <span class="assignment-confirm-file">
                                            <span class="assignment-file-icon"><i data-lucide="file-text"></i></span>
                                            <span><strong>{{ $file['filename'] ?? 'Jawaban tugas' }}</strong><small>{{ ! empty($file['filesize']) ? number_format(((int) $file['filesize']) / 1048576, 2).' MB' : 'File jawaban' }}</small></span>
                                        </span>
                                    @empty
                                        <span>-</span>
                                    @endforelse
                                </dd>
                                <dt>Catatan</dt>
                                <dd>{{ trim((string) ($assignmentAnswer ?? '')) !== '' ? $assignmentAnswer : '-' }}</dd>
                            </dl>
                        </section>

                        <div class="assignment-confirm-actions">
                            @if (! $isSubmitted)
                                <a class="assignment-secondary-button" href="{{ route('courses.modules.show', ['courseId' => $courseId, 'moduleId' => $moduleId, 'mode' => 'work']) }}" data-loading-button data-loading-tone="dark">Batal</a>
                                <form method="POST" action="{{ route('courses.modules.assignment.final-submit', ['courseId' => $courseId, 'moduleId' => $moduleId, 'mode' => 'confirm']) }}">
                                    @csrf
                                    <button class="assignment-primary-button" type="submit" data-loading-button {{ $submissionFiles->isEmpty() && trim((string) ($assignmentAnswer ?? '')) === '' ? 'disabled' : '' }}>Kumpulkan</button>
                                </form>
                            @else
                                <a class="assignment-secondary-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" data-loading-button data-loading-tone="dark">Kembali ke Detail Mata Kuliah</a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>

    @if ($deadlinePassed)
        <div class="material-modal-layer assignment-modal-layer" data-deadline-layer>
            <section class="assignment-alert-modal" role="dialog" aria-modal="true" aria-labelledby="assignment-deadline-title">
                <button class="material-modal-close" type="button" data-deadline-close aria-label="Tutup pemberitahuan batas waktu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg></button>
                <span class="assignment-alert-art is-deadline" aria-hidden="true"><i data-lucide="alarm-clock"></i></span>
                <h2 id="assignment-deadline-title">Waktu Pengumpulan Telah Berakhir</h2>
                <p>Maaf, Anda tidak dapat {{ $pageMode === 'confirm' ? 'mengumpulkan' : 'mengerjakan' }} tugas ini karena batas waktu pengumpulan telah lewat.</p>
                <div class="assignment-deadline-panel">
                    <span><i data-lucide="clock-3"></i></span>
                    <div><small>Batas Pengumpulan</small><strong>{{ $formatTimestamp($deadlineTimestamp) }}</strong></div>
                    <span><i data-lucide="calendar-days"></i></span>
                    <div><small>Waktu Saat Ini</small><strong>{{ now()->timezone($timezone)->format('d / m / Y H:i') }}</strong></div>
                </div>
            </section>
        </div>
    @endif

    <div class="material-modal-layer assignment-modal-layer" data-format-layer {{ $showFormatError ? '' : 'hidden' }}>
        <section class="assignment-alert-modal assignment-format-modal" role="dialog" aria-modal="true" aria-labelledby="assignment-format-title">
            <button class="material-modal-close" type="button" data-format-close aria-label="Tutup pemberitahuan format file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg></button>
            <span class="assignment-alert-art is-format" aria-hidden="true"><i data-lucide="file-warning"></i></span>
            <h2 id="assignment-format-title">Format File Tidak Didukung</h2>
            <p>Format file Anda tidak sesuai dengan yang diizinkan.</p>
            <div class="assignment-format-panel">
                <span><i data-lucide="file-check-2"></i></span>
                <div><small>Format yang diizinkan:</small><strong>{{ $allowedTypesLabel }} (Maks {{ $answerMaxLabel }})</strong></div>
            </div>
        </section>
    </div>

    @if ($pointsAwarded > 0)
        <div class="material-modal-layer material-feedback-layer" data-assignment-points-layer>
            <section class="material-feedback-modal" role="dialog" aria-modal="true" aria-labelledby="assignment-points-title">
                <button class="material-modal-close" type="button" data-assignment-points-close aria-label="Tutup pemberitahuan poin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg></button>
                <div class="material-success-art" aria-hidden="true"><i data-lucide="star"></i></div>
                <h2 id="assignment-points-title">Selamat</h2>
                <p>Kamu telah Mengumpulkan Tugas ini</p>
                <div class="material-points-panel">
                    <span class="material-points-star" aria-hidden="true"><i data-lucide="star"></i></span>
                    <strong>+ {{ $pointsAwarded }} Poin</strong>
                </div>
            </section>
        </div>
    @endif

    @if ($earnedBadge)
        <div class="material-modal-layer material-feedback-layer" data-assignment-badge-layer {{ $pointsAwarded > 0 ? 'hidden' : '' }}>
            <section class="material-feedback-modal material-badge-modal" role="dialog" aria-modal="true" aria-labelledby="assignment-badge-title">
                <button class="material-modal-close" type="button" data-assignment-badge-close aria-label="Tutup pemberitahuan badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg></button>
                <div class="material-badge-art material-badge-art--{{ $badgeSlug }}" aria-label="Badge {{ $earnedBadge['name'] }}"></div>
                <h2 id="assignment-badge-title">Badge Baru Diperoleh</h2>
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
            const body = document.body;
            const lockPage = () => body.classList.add('material-modal-open');
            const unlockPage = () => {
                if (!document.querySelector('.material-modal-layer:not([hidden])')) body.classList.remove('material-modal-open');
            };
            const closeLayer = (layer) => {
                if (layer) layer.hidden = true;
                unlockPage();
            };

            const deadlineLayer = document.querySelector('[data-deadline-layer]');
            const formatLayer = document.querySelector('[data-format-layer]');
            if (deadlineLayer || (formatLayer && !formatLayer.hidden)) lockPage();
            document.querySelector('[data-deadline-close]')?.addEventListener('click', () => closeLayer(deadlineLayer));
            document.querySelector('[data-format-close]')?.addEventListener('click', () => closeLayer(formatLayer));

            const input = document.querySelector('[data-assignment-file-input]');
            const selectedFiles = document.querySelector('[data-selected-files]');
            const dropzone = document.querySelector('[data-assignment-dropzone]');
            const allowedExtensions = @json($allowedExtensions->all());
            const maxBytes = {{ $answerMaxBytes }};

            const showFormatError = () => {
                if (!formatLayer) return;
                formatLayer.hidden = false;
                lockPage();
                formatLayer.querySelector('button')?.focus();
            };

            const formatSize = (bytes) => bytes >= 1048576
                ? `${(bytes / 1048576).toFixed(2)} MB`
                : `${(bytes / 1024).toFixed(1)} KB`;

            const renderFiles = (files) => {
                if (!selectedFiles) return;
                selectedFiles.innerHTML = '';
                files.forEach((file) => {
                    const extension = `.${file.name.split('.').pop()?.toLowerCase() || ''}`;
                    if ((allowedExtensions.length && !allowedExtensions.includes(extension)) || file.size > maxBytes) {
                        input.value = '';
                        showFormatError();
                        return;
                    }

                    const row = document.createElement('div');
                    row.className = 'assignment-selected-file';
                    row.innerHTML = `
                        <span class="assignment-file-icon assignment-file-icon--dynamic" aria-hidden="true"></span>
                        <span><strong></strong><small></small></span>
                        <button type="button" aria-label="Hapus file"><span aria-hidden="true">×</span></button>
                    `;
                    row.querySelector('strong').textContent = file.name;
                    row.querySelector('small').textContent = formatSize(file.size);
                    row.querySelector('button').addEventListener('click', () => {
                        input.value = '';
                        row.remove();
                    });
                    selectedFiles.appendChild(row);
                });
            };

            input?.addEventListener('change', () => renderFiles(Array.from(input.files || [])));
            ['dragenter', 'dragover'].forEach((eventName) => dropzone?.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            }));
            ['dragleave', 'drop'].forEach((eventName) => dropzone?.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            }));
            dropzone?.addEventListener('drop', (event) => {
                if (!input || !event.dataTransfer?.files?.length) return;
                input.files = event.dataTransfer.files;
                renderFiles(Array.from(input.files));
            });

            const pointsLayer = document.querySelector('[data-assignment-points-layer]');
            const badgeLayer = document.querySelector('[data-assignment-badge-layer]');
            if (pointsLayer || (badgeLayer && !badgeLayer.hidden)) lockPage();
            document.querySelector('[data-assignment-points-close]')?.addEventListener('click', () => {
                if (pointsLayer) pointsLayer.hidden = true;
                if (badgeLayer) {
                    badgeLayer.hidden = false;
                    badgeLayer.querySelector('button')?.focus();
                } else unlockPage();
            });
            document.querySelector('[data-assignment-badge-close]')?.addEventListener('click', () => closeLayer(badgeLayer));
        })();
    </script>
</body>
</html>
