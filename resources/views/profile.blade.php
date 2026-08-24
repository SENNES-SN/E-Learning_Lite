<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Informasi profil mahasiswa E-Learning Lite.">
    <title>Profil Pengguna - E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-shell-page profile-final-page">
    @php
        use Illuminate\Support\Str;
        $displayName = trim((string) ($user->name ?? $username ?? 'Mahasiswa')) ?: 'Mahasiswa';
        $initials = collect(explode(' ', $displayName))
            ->filter()
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    @endphp

    <div class="student-shell">
        @include('partials.sidebar', ['activeNav' => 'profile'])

        <main class="student-main">
            @include('partials.student_topbar')

            <div class="student-page-content profile-final-content">
                <header class="student-section-header">
                    <span class="student-section-header-icon" aria-hidden="true">
                        <i data-lucide="circle-user-round"></i>
                    </span>
                    <div>
                        <h1>Profil Pengguna</h1>
                        <p>Informasi akun yang digunakan dalam pembelajaran.</p>
                    </div>
                    <a class="final-back-button" href="{{ route('dashboard') }}" aria-label="Kembali ke dashboard" data-loading-button data-loading-tone="dark">
                        <i data-lucide="undo-2" aria-hidden="true"></i>
                    </a>
                </header>

                <section class="profile-final-card" aria-labelledby="profile-name">
                    <div class="profile-final-identity">
                        <span class="profile-final-avatar" aria-hidden="true">{{ $initials ?: 'M' }}</span>
                        <div>
                            <p>Mahasiswa</p>
                            <h2 id="profile-name" data-profile-fit-name>{{ $displayName }}</h2>
                        </div>
                    </div>

                    <dl class="profile-final-details">
                        <div>
                            <dt><i data-lucide="user-round" aria-hidden="true"></i> Nama Lengkap</dt>
                            <dd>{{ $displayName }}</dd>
                        </div>
                        <div>
                            <dt><i data-lucide="mail" aria-hidden="true"></i> Email</dt>
                            <dd>{{ $user->email ?? 'Belum tersedia' }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </main>
    </div>

    <script>
        (() => {
            const profileName = document.querySelector('[data-profile-fit-name]');
            if (!profileName) return;

            const fitProfileName = () => {
                profileName.classList.remove('is-single-line');
                profileName.style.removeProperty('font-size');

                if (!window.matchMedia('(max-width: 760px)').matches) return;

                const computedStyle = window.getComputedStyle(profileName);
                const lineHeight = Number.parseFloat(computedStyle.lineHeight);
                const hasMultipleLines = profileName.getBoundingClientRect().height > lineHeight * 1.4;
                if (!hasMultipleLines) return;

                profileName.classList.add('is-single-line');
                let fontSize = Number.parseFloat(computedStyle.fontSize);
                const minimumFontSize = 11;

                while (profileName.scrollWidth > profileName.clientWidth && fontSize > minimumFontSize) {
                    fontSize = Math.max(minimumFontSize, fontSize - 0.5);
                    profileName.style.setProperty('font-size', `${fontSize}px`, 'important');
                }
            };

            let resizeFrame;
            window.addEventListener('resize', () => {
                window.cancelAnimationFrame(resizeFrame);
                resizeFrame = window.requestAnimationFrame(fitProfileName);
            }, { passive: true });

            fitProfileName();
            document.fonts?.ready.then(fitProfileName);
        })();
    </script>
</body>
</html>
