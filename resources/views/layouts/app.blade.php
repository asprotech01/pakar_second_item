<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tilik | Pemeriksaan perangkat bekas')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Tilik, beranda">
                <span class="brand-mark" aria-hidden="true">T</span>
                <span class="brand-name">tilik<span>.</span></span>
            </a>
            <nav class="main-nav" aria-label="Navigasi utama">
                @auth
                    <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Ruang kerja</a>
                    <a class="{{ request()->routeIs('devices.*') ? 'is-active' : '' }}" href="{{ route('devices.index') }}">Katalog</a>
                    <a class="{{ request()->routeIs('assessments.history') ? 'is-active' : '' }}" href="{{ route('assessments.history') }}">Riwayat</a>
                    @if (Auth::user()?->isAdmin())
                        <a class="{{ request()->routeIs('admin.*') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">Admin</a>
                    @endif
                @else
                    <a class="login-link" href="{{ route('login') }}" aria-label="Masuk untuk pengguna dan admin">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <path d="M10 17l5-5-5-5"></path>
                            <path d="M15 12H3"></path>
                        </svg>
                        <span>Masuk</span>
                    </a>
                    <a href="{{ route('register') }}">Daftar</a>
                @endauth
            </nav>
            <div class="topbar-meta">
                @auth
                    <span class="status-dot"></span>
                    {{ Auth::user()->isAdmin() ? 'ADMIN MODE' : 'USER MODE' }}
                    <form method="POST" action="{{ route('logout') }}" class="topbar-logout-form">
                        @csrf
                        <button type="submit" class="button button-quiet topbar-logout-button">Keluar</button>
                    </form>
                @else
                    <span class="status-dot"></span> MESIN ATURAN AKTIF
                @endauth
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="site-footer">
            <span>tilik. <span class="footer-muted">Penilaian kondisi teknis perangkat bekas</span></span>
            <span>Forward chaining <i>·</i> Certainty factor</span>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>