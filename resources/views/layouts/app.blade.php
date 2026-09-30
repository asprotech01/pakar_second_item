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
                <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Ruang kerja</a>
                <a class="{{ request()->routeIs('assessments.history') ? 'is-active' : '' }}" href="{{ route('assessments.history') }}">Riwayat</a>
            </nav>
            <div class="topbar-meta"><span class="status-dot"></span> MESIN ATURAN AKTIF</div>
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