<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Masuk ke E-Learning Lite untuk melanjutkan pembelajaran.">
    <title>Login E-Learning Lite</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="login-page">
    <main class="login-layout">
        <section class="login-brand-panel" aria-labelledby="product-name">
            <div class="login-brand-content">
                <div class="login-brand-mark" aria-hidden="true">
                    <i data-lucide="graduation-cap"></i>
                </div>
                <h1 id="product-name">E-Learning Lite</h1>
                <p>Belajar lebih mudah, capai prestasi bersama</p>
            </div>
        </section>

        <section class="login-form-panel" aria-labelledby="login-title">
            <div class="login-card{{ $errors->any() ? ' has-feedback' : '' }}">
                <div class="login-card-heading">
                    <h2 id="login-title">Login</h2>
                    <p>Masuk untuk melanjutkan pembelajaran</p>
                </div>

                <form class="login-form" method="POST" action="{{ route('login.attempt') }}">
                    @csrf

                    <div class="login-field">
                        <label for="username">Username</label>
                        <div class="login-input-shell">
                            <i data-lucide="user" aria-hidden="true"></i>
                            <input
                                id="username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                placeholder="Masukkan Username"
                                autocomplete="username"
                                required>
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="login-input-shell">
                            <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Masukkan Password"
                                autocomplete="current-password"
                                required>
                            <button
                                class="login-password-toggle"
                                type="button"
                                data-password-toggle
                                aria-controls="password"
                                aria-label="Tampilkan password">
                                <i class="password-hidden-icon" data-lucide="eye-off" aria-hidden="true"></i>
                                <i class="password-visible-icon" data-lucide="eye" aria-hidden="true" hidden></i>
                            </button>
                        </div>
                    </div>
                    <button class="login-submit" type="submit" data-loading-button>Login</button>
                </form>
            </div>

            @if ($errors->any())
            <div class="login-feedback-overlay" data-login-feedback>
                <div class="login-feedback" role="alert" aria-live="assertive">
                    {{ $errors->first() }}
                </div>
            </div>
            @endif
        </section>
    </main>
</body>

</html>
