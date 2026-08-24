<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Baca dan selesaikan materi pembelajaran.">
    <title>{{ $module['name'] ?? 'Detail Materi' }} - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page final-material-page">
    @php
        $materialName = $module['name'] ?? 'Detail Materi';
        $description = trim(strip_tags((string) ($module['description'] ?? $module['intro'] ?? '')));
        $description = $description !== '' ? $description : 'Materi ini belum memiliki deskripsi.';
        $completionStatuses = collect($courseProgress['statuses'] ?? [])
            ->filter(fn ($status) => is_array($status))
            ->keyBy(fn ($status) => (int) ($status['cmid'] ?? $status['coursemoduleid'] ?? 0));
        $materialStatus = $completionStatuses->get((int) $moduleId, []);
        $completionState = (int) ($materialStatus['state'] ?? $materialStatus['completionstate'] ?? $module['completiondata']['state'] ?? 0);
        $materialIsCompleted = in_array($completionState, [1, 2, 3], true) || (bool) ($materialStatus['completed'] ?? false);
        $materialContents = collect($module['contents'] ?? [])->filter(fn ($content) => is_array($content))->values();
        $primaryContent = $materialContents->first();
        $primaryFilename = $primaryContent['filename'] ?? $materialName;
        $primaryExtension = strtolower(pathinfo((string) $primaryFilename, PATHINFO_EXTENSION));
        $primaryFileUrl = $primaryContent['fileurl'] ?? null;
        if ($primaryFileUrl && $moodleToken) {
            $primaryFileUrl .= str_contains($primaryFileUrl, '?') ? '&token='.urlencode($moodleToken) : '?token='.urlencode($moodleToken);
        }
        $primaryPreviewType = match (true) {
            $primaryExtension === 'pdf' => 'pdf',
            in_array($primaryExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) => 'image',
            in_array($primaryExtension, ['txt', 'csv', 'log'], true) => 'text',
            default => 'content',
        };
        $primaryPreviewUrl = $primaryFileUrl && $primaryPreviewType !== 'content'
            ? route('moodle.file.preview', ['url' => $primaryFileUrl, 'filename' => $primaryFilename])
            : null;
        $completionFeedback = session('material_completion_feedback');
        $pointsAwarded = (int) ($completionFeedback['points_awarded'] ?? 0);
        $earnedBadge = is_array($completionFeedback['badge'] ?? null) ? $completionFeedback['badge'] : null;
        $badgeSlug = in_array($earnedBadge['slug'] ?? '', ['knowledge', 'goal', 'perfection'], true)
            ? $earnedBadge['slug']
            : 'knowledge';
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content final-material-content">
                <header class="final-material-header">
                    <span class="final-material-title-icon" aria-hidden="true">
                        <span class="asset-icon asset-icon-material"></span>
                    </span>
                    <div class="final-activity-heading">
                        <h1>{{ $materialName }}</h1>
                        <span class="final-material-status {{ $materialIsCompleted ? 'is-completed' : 'is-pending' }}">
                            {{ $materialIsCompleted ? 'Sudah Diselesaikan' : 'Belum Diselesaikan' }}
                        </span>
                    </div>
                    <a class="final-back-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-label="Kembali ke detail mata kuliah" data-loading-button data-loading-tone="dark">
                        <i data-lucide="undo-2" aria-hidden="true"></i>
                    </a>
                </header>

                @if ($contentError)
                    <div class="material-page-feedback is-error" role="alert">Materi belum dapat ditampilkan. Silakan coba lagi beberapa saat.</div>
                @endif

                @if ($errors->any())
                    <div class="material-page-feedback is-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <section class="final-material-section" aria-labelledby="material-attachment-heading">
                    <h2 id="material-attachment-heading">Lampiran</h2>

                    @if ($materialContents->isNotEmpty())
                        <div class="final-material-files">
                            @foreach ($materialContents as $content)
                                @php
                                    $filename = $content['filename'] ?? 'Lampiran materi';
                                    $fileUrl = $content['fileurl'] ?? null;
                                    if ($fileUrl && $moodleToken) {
                                        $fileUrl .= str_contains($fileUrl, '?') ? '&token='.urlencode($moodleToken) : '?token='.urlencode($moodleToken);
                                    }
                                    $size = (int) ($content['filesize'] ?? 0);
                                    $sizeLabel = $size >= 1048576
                                        ? number_format($size / 1048576, 2).' MB'
                                        : ($size > 0 ? number_format($size / 1024, 1).' KB' : 'Ukuran tidak tersedia');
                                @endphp
                                <article class="final-material-file">
                                    <span class="final-material-file-icon" aria-hidden="true"><i data-lucide="file-text"></i></span>
                                    <span class="final-material-file-copy">
                                        <strong>{{ $filename }}</strong>
                                        <small>{{ $sizeLabel }}</small>
                                    </span>
                                    @if ($fileUrl)
                                        <a class="final-material-download" href="{{ route('moodle.file.download', ['url' => $fileUrl, 'filename' => $filename]) }}" aria-label="Unduh {{ $filename }}">
                                            <i data-lucide="download" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="final-material-empty">Materi ini tidak memiliki lampiran terpisah.</div>
                    @endif
                </section>

                <section class="final-material-section final-material-description" aria-labelledby="material-description-heading">
                    <h2 id="material-description-heading">Deskripsi Materi</h2>
                    <p>{{ $description }}</p>
                </section>

                <div class="final-material-actions">
                    <button class="final-material-read-button" type="button" data-reader-open>
                        {{ $materialIsCompleted ? 'Baca Ulang' : 'Baca Materi' }}
                    </button>
                </div>
            </div>
        </main>
    </div>

    <form id="material-complete-form" method="POST" action="{{ route('courses.modules.material.complete', ['courseId' => $courseId, 'moduleId' => $moduleId]) }}">
        @csrf
    </form>

    <div class="material-modal-layer material-reader-layer" data-reader-layer hidden>
        <section class="material-reader" role="dialog" aria-modal="true" aria-labelledby="material-reader-title">
            <header class="material-reader-header">
                <h2 id="material-reader-title">{{ $primaryFilename }}</h2>
            </header>
            <div
                class="material-reader-body"
                data-reader-body
                data-preview-url="{{ $primaryPreviewUrl }}"
                data-preview-type="{{ $primaryPreviewType }}"
            >
                <div class="material-reader-loading" data-reader-loading>
                    <i data-lucide="loader-circle" aria-hidden="true"></i>
                    <span>Menyiapkan materi...</span>
                </div>
                <article class="material-reader-fallback" data-reader-fallback hidden>
                    <h3>{{ $materialName }}</h3>
                    <p>{{ $description }}</p>
                </article>
            </div>
            <footer class="material-reader-footer">
                <strong data-reader-page-label>{{ $primaryPreviewType === 'pdf' ? 'Dokumen materi' : 'Materi pembelajaran' }}</strong>
                <div class="material-reader-actions">
                    <button class="material-reader-button is-secondary" type="button" data-reader-close>Kembali</button>
                    @if ($materialIsCompleted)
                        <button class="material-reader-button is-primary" type="button" data-reader-close>Selesai</button>
                    @else
                        <button class="material-reader-button is-primary" type="submit" form="material-complete-form" data-loading-button>Selesai</button>
                    @endif
                </div>
            </footer>
        </section>
    </div>

    @if ($pointsAwarded > 0)
        <div class="material-modal-layer material-feedback-layer" data-points-layer>
            <section class="material-feedback-modal" role="dialog" aria-modal="true" aria-labelledby="material-points-title">
                <button class="material-modal-close" type="button" data-points-close aria-label="Tutup pemberitahuan poin">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                </button>
                <div class="material-success-art" aria-hidden="true">
                    <i data-lucide="star"></i>
                </div>
                <h2 id="material-points-title">Selamat</h2>
                <p>Kamu telah menyelesaikan materi ini</p>
                <div class="material-points-panel">
                    <span class="material-points-star" aria-hidden="true"><i data-lucide="star"></i></span>
                    <strong>+ {{ $pointsAwarded }} Poin</strong>
                </div>
            </section>
        </div>
    @endif

    @if ($earnedBadge)
        <div class="material-modal-layer material-feedback-layer" data-badge-layer {{ $pointsAwarded > 0 ? 'hidden' : '' }}>
            <section class="material-feedback-modal material-badge-modal" role="dialog" aria-modal="true" aria-labelledby="material-badge-title">
                <button class="material-modal-close" type="button" data-badge-close aria-label="Tutup pemberitahuan badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                </button>
                <div class="material-badge-art material-badge-art--{{ $badgeSlug }}" aria-label="Badge {{ $earnedBadge['name'] }}"></div>
                <h2 id="material-badge-title">Badge Baru Diperoleh</h2>
                <p>Selamat kamu telah memperoleh<br>badge baru atas pencapaianmu</p>
                <div class="material-badge-card">
                    <div class="material-badge-mini material-badge-art--{{ $badgeSlug }}" aria-hidden="true"></div>
                    <div>
                        <strong>{{ $earnedBadge['name'] }}</strong>
                        <span>{{ $earnedBadge['description'] }}</span>
                    </div>
                </div>
            </section>
        </div>
    @endif

    <script>
        (() => {
            const readerLayer = document.querySelector('[data-reader-layer]');
            const readerBody = document.querySelector('[data-reader-body]');
            const readerLoading = document.querySelector('[data-reader-loading]');
            const readerFallback = document.querySelector('[data-reader-fallback]');
            const openButton = document.querySelector('[data-reader-open]');
            const closeButtons = document.querySelectorAll('[data-reader-close]');
            let readerLoaded = false;

            const setPageLocked = (locked) => document.body.classList.toggle('material-modal-open', locked);

            const base64ToBlobUrl = (base64, contentType) => {
                const binary = window.atob(base64);
                const bytes = new Uint8Array(binary.length);
                for (let index = 0; index < binary.length; index += 1) {
                    bytes[index] = binary.charCodeAt(index);
                }
                return URL.createObjectURL(new Blob([bytes], { type: contentType || 'application/octet-stream' }));
            };

            const loadReader = async () => {
                if (readerLoaded || !readerBody) return;
                readerLoaded = true;
                const previewUrl = readerBody.dataset.previewUrl;
                const previewType = readerBody.dataset.previewType;

                if (!previewUrl || !['pdf', 'image', 'text'].includes(previewType)) {
                    readerLoading?.setAttribute('hidden', '');
                    readerFallback?.removeAttribute('hidden');
                    return;
                }

                try {
                    const response = await fetch(previewUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Materi belum dapat dimuat.');
                    const payload = await response.json();
                    const blobUrl = base64ToBlobUrl(payload.content, payload.content_type);
                    let preview;

                    if (previewType === 'image') {
                        preview = document.createElement('img');
                        preview.alt = payload.filename || 'Materi pembelajaran';
                    } else if (previewType === 'text') {
                        preview = document.createElement('iframe');
                        preview.title = payload.filename || 'Materi pembelajaran';
                    } else {
                        preview = document.createElement('iframe');
                        preview.title = payload.filename || 'Dokumen materi';
                    }

                    preview.className = 'material-reader-preview';
                    preview.src = blobUrl;
                    readerLoading?.setAttribute('hidden', '');
                    readerBody.appendChild(preview);
                } catch (error) {
                    readerLoading?.setAttribute('hidden', '');
                    if (readerFallback) {
                        readerFallback.removeAttribute('hidden');
                        const message = document.createElement('p');
                        message.className = 'material-reader-error';
                        message.textContent = 'Pratinjau belum dapat dimuat. Kamu tetap dapat membaca ringkasan materi ini.';
                        readerFallback.prepend(message);
                    }
                }
            };

            const openReader = () => {
                if (!readerLayer) return;
                readerLayer.hidden = false;
                setPageLocked(true);
                loadReader();
                readerLayer.querySelector('button')?.focus();
            };

            const closeReader = () => {
                if (!readerLayer) return;
                readerLayer.hidden = true;
                setPageLocked(false);
                openButton?.focus();
            };

            openButton?.addEventListener('click', openReader);
            closeButtons.forEach((button) => button.addEventListener('click', closeReader));

            const pointsLayer = document.querySelector('[data-points-layer]');
            const badgeLayer = document.querySelector('[data-badge-layer]');
            const closePoints = () => {
                if (pointsLayer) pointsLayer.hidden = true;
                if (badgeLayer) {
                    badgeLayer.hidden = false;
                    badgeLayer.querySelector('button')?.focus();
                } else {
                    setPageLocked(false);
                }
            };
            const closeBadge = () => {
                if (badgeLayer) badgeLayer.hidden = true;
                setPageLocked(false);
            };

            if (pointsLayer || (badgeLayer && !badgeLayer.hidden)) setPageLocked(true);
            document.querySelector('[data-points-close]')?.addEventListener('click', closePoints);
            document.querySelector('[data-badge-close]')?.addEventListener('click', closeBadge);

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                if (readerLayer && !readerLayer.hidden) closeReader();
                else if (pointsLayer && !pointsLayer.hidden) closePoints();
                else if (badgeLayer && !badgeLayer.hidden) closeBadge();
            });
        })();
    </script>
</body>
</html>
