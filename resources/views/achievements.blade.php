<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Poin, badge, dan leaderboard mata kuliah.">
    <title>Pencapaian Saya - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page achievement-page">
    @php
        $points = max(0, (int) ($achievement['points'] ?? 0));
        $badges = collect($achievement['badges'] ?? [])->filter(fn ($badge) => is_array($badge))->values();
        $leaderboardRows = collect($leaderboard['rows'] ?? [])->filter(fn ($row) => is_array($row))->values();
        $topRows = $leaderboardRows->take(3);
        $currentRow = $leaderboardRows->first(fn ($row) => (bool) ($row['is_current'] ?? false));
        $visibleRows = $topRows;
        if (is_array($currentRow) && ! $topRows->contains(fn ($row) => (int) ($row['id'] ?? 0) === (int) ($currentRow['id'] ?? 0))) {
            $visibleRows = $topRows->push($currentRow);
        }
        $currentRank = ($leaderboard['complete'] ?? false) ? (int) ($leaderboard['current_rank'] ?? 0) : 0;
        $totalStudents = max($leaderboardRows->count(), (int) ($leaderboard['total_students'] ?? 0));
        $formatPoints = fn (int $value) => number_format($value, 0, ',', '.');
        $formatEarnedDate = fn ($timestamp) => $timestamp
            ? \Carbon\Carbon::createFromTimestamp((int) $timestamp)->locale('id')->translatedFormat('d F Y')
            : null;
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'moodle'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content achievement-content">
                <header class="achievement-header">
                    <span class="achievement-header-icon" aria-hidden="true"><i data-lucide="award"></i></span>
                    <h1>Pencapaian Saya</h1>
                    <a class="final-back-button" href="{{ route('courses.show', ['courseId' => $courseId]) }}" aria-label="Kembali ke detail mata kuliah"><i data-lucide="undo-2"></i></a>
                </header>

                @if ($achievementError)
                    <div class="student-inline-error" role="alert">{{ $achievementError }}</div>
                @endif

                <section class="achievement-summary" aria-label="Ringkasan pencapaian">
                    <article class="points-summary">
                        <h2>Poin Saya</h2>
                        <div><span aria-hidden="true"><i data-lucide="star"></i></span><strong>{{ $formatPoints($points) }}</strong></div>
                        <p>Total poin yang telah kamu kumpulkan</p>
                    </article>
                    <article class="rank-summary">
                        <h2>Peringkat Anda</h2>
                        <div><span aria-hidden="true"><i data-lucide="trophy"></i></span><strong>{{ $currentRank > 0 ? '# '.$currentRank : '—' }}</strong></div>
                        <p>Dari {{ $totalStudents }} Mahasiswa</p>
                    </article>
                </section>

                <section class="achievement-badges-panel" aria-labelledby="my-badges-title">
                    <div class="achievement-section-heading">
                        <h2 id="my-badges-title">Badge Saya</h2>
                        <button type="button" data-all-badges-open>Selengkapnya <i data-lucide="arrow-right"></i></button>
                    </div>
                    <div class="badge-grid">
                        @foreach ($badges->take(4) as $badge)
                            <article class="achievement-badge-card">
                                <div class="achievement-badge-art achievement-badge-art--{{ $badge['slug'] }}" aria-label="Badge {{ $badge['name'] }}"></div>
                                <h3>{{ $badge['name'] }}</h3>
                                <p>{{ $badge['description'] }}</p>
                                <span class="achievement-badge-status {{ $badge['earned'] ? 'is-earned' : '' }}">{{ $badge['earned'] ? 'Diperoleh' : 'Belum Diperoleh' }}</span>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="leaderboard-panel" aria-labelledby="leaderboard-title">
                    <h2 id="leaderboard-title">Leaderboard</h2>
                    @if ($visibleRows->isNotEmpty())
                        <div class="leaderboard-list">
                            @foreach ($visibleRows as $row)
                                <article class="leaderboard-row {{ $row['is_current'] ? 'is-current' : '' }}">
                                    <span class="leaderboard-rank {{ (int) $row['rank'] <= 3 ? 'rank-'.(int) $row['rank'] : 'rank-other' }}">{{ $row['rank'] }}</span>
                                    <span class="leaderboard-avatar" aria-hidden="true"><i data-lucide="circle-user-round"></i></span>
                                    <strong>{{ $row['is_current'] ? 'Anda' : $row['name'] }}</strong>
                                    <span class="leaderboard-points">{{ $formatPoints((int) $row['points']) }}</span>
                                    <span class="leaderboard-star" aria-hidden="true"><i data-lucide="star"></i></span>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p class="leaderboard-empty">Leaderboard belum tersedia.</p>
                    @endif
                    @if ($leaderboard['message'] ?? null)
                        <p class="leaderboard-note">{{ $leaderboard['message'] }}</p>
                    @endif
                </section>
            </div>
        </main>
    </div>

    <div class="achievement-modal-layer" data-all-badges-layer hidden>
        <section class="all-badges-modal" role="dialog" aria-modal="true" aria-labelledby="all-badges-title">
            <header>
                <h2 id="all-badges-title">Semua Badge</h2>
                <button type="button" data-all-badges-close aria-label="Tutup daftar semua badge"><i data-lucide="x"></i></button>
            </header>
            <div class="badge-list">
                @foreach ($badges as $badge)
                    <article class="badge-list-item">
                        <div class="achievement-badge-art achievement-badge-list-art achievement-badge-art--{{ $badge['slug'] }}" aria-hidden="true"></div>
                        <div class="badge-list-copy">
                            <h3>{{ $badge['name'] }}</h3>
                            <p>{{ $badge['description'] }}</p>
                        </div>
                        <div class="badge-earned-state {{ $badge['earned'] ? 'is-earned' : '' }}">
                            <span>@if ($badge['earned'])<i data-lucide="circle-check"></i>@endif {{ $badge['earned'] ? 'Diperoleh' : 'Belum Diperoleh' }}</span>
                            @if ($formatEarnedDate($badge['earned_at'] ?? null))
                                <small>{{ $formatEarnedDate($badge['earned_at']) }}</small>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>

    <script>
        (() => {
            const trigger = document.querySelector('[data-all-badges-open]');
            const layer = document.querySelector('[data-all-badges-layer]');
            const closeButton = document.querySelector('[data-all-badges-close]');
            const focusableSelector = 'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            const openModal = () => {
                layer.hidden = false;
                document.body.classList.add('achievement-modal-open');
                closeButton.focus();
            };

            const closeModal = () => {
                layer.hidden = true;
                document.body.classList.remove('achievement-modal-open');
                trigger.focus();
            };

            trigger?.addEventListener('click', openModal);
            closeButton?.addEventListener('click', closeModal);
            layer?.addEventListener('click', (event) => {
                if (event.target === layer) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (!layer || layer.hidden) return;
                if (event.key === 'Escape') {
                    closeModal();
                    return;
                }
                if (event.key !== 'Tab') return;
                const focusable = Array.from(layer.querySelectorAll(focusableSelector));
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        })();
    </script>
</body>
</html>
