<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pengumuman dan forum diskusi mata kuliah.">
    <title>{{ $module['name'] ?? 'Forum Diskusi' }} - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page discussion-detail-page">
    @php
        $discussionName = trim((string) ($module['name'] ?? 'Forum Diskusi')) ?: 'Forum Diskusi';
        $description = trim(strip_tags((string) ($module['description'] ?? $module['intro'] ?? '')));
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content discussion-detail-content">
                <header class="discussion-detail-header">
                    <span class="discussion-detail-icon" aria-hidden="true">
                        <i data-lucide="messages-square"></i>
                    </span>
                    <div>
                        <p>{{ $course['fullname'] ?? 'Mata Kuliah' }}</p>
                        <h1>{{ $discussionName }}</h1>
                    </div>
                    <a class="final-back-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-label="Kembali ke detail mata kuliah" data-loading-button data-loading-tone="dark">
                        <i data-lucide="undo-2" aria-hidden="true"></i>
                    </a>
                </header>

                @if ($contentError)
                    <div class="student-inline-error" role="alert">
                        Forum diskusi belum dapat dimuat. Silakan coba lagi beberapa saat.
                    </div>
                @endif

                <section class="discussion-detail-card" aria-labelledby="discussion-title">
                    <div class="discussion-detail-card-heading">
                        <span aria-hidden="true"><i data-lucide="message-circle"></i></span>
                        <div>
                            <h2 id="discussion-title">Forum Diskusi</h2>
                            <p>Ruang pengumuman dan diskusi mata kuliah.</p>
                        </div>
                    </div>

                    <div class="discussion-detail-copy">
                        @if ($description !== '')
                            <p>{!! nl2br(e($description)) !!}</p>
                        @else
                            <p>Belum ada pengumuman atau topik diskusi yang ditampilkan.</p>
                        @endif
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
