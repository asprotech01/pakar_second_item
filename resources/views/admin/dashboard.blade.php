@extends('layouts.app')

@section('title', 'Dashboard Admin | Tilik')

@section('content')
<div class="page-wrap admin-page">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand-wrap">
                <div class="brand-mark">T</div>
                <div>
                    <p class="eyebrow admin-eyebrow">ADMIN CONSOLE</p>
                    <h2>tilik<span>.</span></h2>
                </div>
            </div>

            <nav class="admin-nav" aria-label="Sidebar admin">
                <a class="admin-nav-item is-active" href="{{ route('admin.dashboard') }}">
                    <span>Dashboard</span>
                </a>
                <a class="admin-nav-item" href="{{ route('admin.knowledge-base.index') }}">
                    <span>Knowledge Base</span>
                </a>
                <a class="admin-nav-item" href="{{ route('dashboard') }}">
                    <span>Ruang kerja pengguna</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <p class="eyebrow admin-eyebrow">AKUN</p>
                <strong>{{ Auth::user()->name }}</strong>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button button-quiet admin-logout">Keluar</button>
                </form>
            </div>
        </aside>

        <main class="admin-content">
            <header class="admin-topbar">
                <div>
                    <p class="eyebrow">DASHBOARD</p>
                    <h1>Overview operasional</h1>
                </div>
                <a class="button button-dark" href="{{ route('admin.knowledge-base.index') }}">Kelola data</a>
            </header>

            <section class="admin-metrics-grid">
                <article class="admin-stat-card">
                    <p class="eyebrow">TOTAL ASESMEN</p>
                    <h2>{{ $stats['assessments_total'] }}</h2>
                    <span>Semua sesi yang tercatat</span>
                </article>
                <article class="admin-stat-card">
                    <p class="eyebrow">SELESAI</p>
                    <h2>{{ $stats['assessments_completed'] }}</h2>
                    <span>Sudah dievaluasi</span>
                </article>
                <article class="admin-stat-card">
                    <p class="eyebrow">FAKTA</p>
                    <h2>{{ $stats['facts_total'] }}</h2>
                    <span>Basis pengetahuan aktif</span>
                </article>
                <article class="admin-stat-card">
                    <p class="eyebrow">RULE</p>
                    <h2>{{ $stats['rules_total'] }}</h2>
                    <span>{{ $stats['rules_active'] }} aktif</span>
                </article>
            </section>

            <section class="admin-panels-grid">
                <article class="admin-panel">
                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">AKTIVITAS</p>
                            <h3>Asesmen terbaru</h3>
                        </div>
                    </div>
                    @if ($recentAssessments->isEmpty())
                        <div class="empty-history compact"><span class="empty-history-mark">—</span><span>Belum ada asesmen.</span></div>
                    @else
                        <div class="admin-list">
                            @foreach ($recentAssessments as $assessment)
                                <div class="admin-list-item">
                                    <div>
                                        <strong>{{ data_get($assessment->device_details, 'label') ?: $assessment->deviceType?->name ?: 'Perangkat' }}</strong>
                                        <small>{{ $assessment->created_at->format('d M Y · H:i') }}</small>
                                    </div>
                                    <span class="result-pill result-{{ optional($assessment->result)->classification ?? 'pending' }}">
                                        {{ optional($assessment->result)->classification ? str_replace('_', ' ', $assessment->result->classification) : 'Berjalan' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>

                <article class="admin-panel">
                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">OPERASIONAL</p>
                            <h3>Prioritas admin</h3>
                        </div>
                    </div>
                    <ul class="admin-checklist">
                        <li>Review fakta baru dan klasifikasi risiko.</li>
                        <li>Validasi rule dengan confidence factor.</li>
                        <li>Pastikan daftar device type dan inspection ready untuk pengguna.</li>
                    </ul>
                </article>
            </section>
        </main>
    </div>
</div>
@endsection
