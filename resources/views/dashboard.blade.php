@extends('layouts.app')

@section('title', 'Ruang kerja | Tilik')

@section('content')
<div class="page-wrap dashboard-page">
    <section class="welcome-row">
        <div>
            <p class="eyebrow">RUANG INSPEKSI <span class="eyebrow-rule"></span> {{ now()->format('d.m.Y') }}</p>
            <h1>Keputusan baik<br><span>dimulai dari pemeriksaan.</span></h1>
            <p class="intro-copy">Catat kondisi perangkat. Tilik menyusun temuan dan tingkat keyakinan dari bukti yang kamu masukkan.</p>
        </div>
        <div class="summary-stamp">
            <span class="stamp-number">{{ str_pad((string) $assessmentCount, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="stamp-label">ASESMEN<br>TERCATAT</span>
        </div>
    </section>

    <section class="hero-band" aria-label="Ringkasan mesin evaluasi">
        <div class="hero-copy">
            <p class="eyebrow eyebrow-light">PENILAIAN TEKNIS · ANDROID & IPHONE</p>
            <h2>Periksa yang terlihat.<br><em>Catat juga yang belum.</em></h2>
            <p>Informasi yang belum diketahui tidak dianggap normal. Hasil akan menunjukkan data yang masih perlu diverifikasi.</p>
            <a class="text-link light-link" href="#pilih-perangkat">Mulai pemeriksaan <span aria-hidden="true">↘</span></a>
        </div>
        <div class="hero-photo-wrap" aria-hidden="true">
            <img class="hero-photo" src="{{ asset('images/phone-inspection-hero.jpg') }}" alt="">
            <span class="hero-photo-label">HARDWARE CHECK <i>01 / 12</i></span>
            <span class="hero-photo-caption">Periksa layar.<br>Balik perangkat.<br>Catat temuannya.</span>
        </div>
        <div class="hero-index">01 <span>/</span> 03</div>
    </section>

    <section class="metrics-row" aria-label="Ringkasan sistem">
        <div class="metric-item"><span class="metric-value">{{ $completedCount }}</span><span class="metric-label">evaluasi selesai</span></div>
        <div class="metric-item"><span class="metric-value">{{ $deviceTypes->sum('questions_count') }}</span><span class="metric-label">pemeriksaan aktif</span></div>
        <div class="metric-item"><span class="metric-value">2</span><span class="metric-label">platform perangkat</span></div>
        <div class="metric-method"><span class="method-mark">CF</span><span><strong>Forward chaining</strong><small>Evidence digabungkan dengan certainty factor</small></span></div>
    </section>

    <section class="device-section" id="pilih-perangkat">
        <div class="section-heading">
            <div><p class="eyebrow">MULAI BARU <span class="eyebrow-rule"></span> 01</p><h2>Pilih jenis perangkat</h2></div>
            <span class="section-aside">Estimasi 3–5 menit · Simpan hasil otomatis</span>
        </div>
        <div class="device-grid">
            @forelse ($deviceTypes as $deviceType)
                <form class="device-card {{ $deviceType->platform === 'ios' ? 'device-card-ios' : '' }}" method="POST" action="{{ route('assessments.store') }}">
                    @csrf
                    <input type="hidden" name="device_type_id" value="{{ $deviceType->id }}">
                    <div class="device-card-top">
                        <span class="platform-tag">{{ $deviceType->platform === 'ios' ? 'APPLE · IOS' : 'GOOGLE · ANDROID' }}</span>
                        <span class="device-arrow" aria-hidden="true">↗</span>
                    </div>
                    <div class="device-illustration {{ $deviceType->platform === 'ios' ? 'illustration-apple' : '' }}" aria-hidden="true">
                        <img src="{{ asset($deviceType->platform === 'ios' ? 'images/phone-device-detail.jpg' : 'images/phone-inspection-hero.jpg') }}" alt="">
                    </div>
                    <div class="device-card-title"><h3>{{ $deviceType->name }}</h3><span>{{ str_pad((string) $deviceType->questions_count, 2, '0', STR_PAD_LEFT) }} cek</span></div>
                    <label class="device-label-field">
                        <span>Nama/model perangkat <small>opsional</small></span>
                        <input type="text" name="device_label" maxlength="100" placeholder="Contoh: Galaxy A54">
                    </label>
                    <button class="button button-dark" type="submit">Mulai inspeksi <span aria-hidden="true">→</span></button>
                </form>
            @empty
                <div class="empty-state">Belum ada tipe perangkat aktif. Jalankan seeder knowledge base untuk menyiapkan Android dan iPhone.</div>
            @endforelse
        </div>
    </section>

    <section class="recent-section">
        <div class="section-heading">
            <div><p class="eyebrow">AKTIVITAS TERBARU <span class="eyebrow-rule"></span> 02</p><h2>Asesmen terakhir</h2></div>
            <a class="text-link" href="{{ route('assessments.history') }}">Lihat semua <span aria-hidden="true">→</span></a>
        </div>
        @if ($recentAssessments->isEmpty())
            <div class="empty-history"><span class="empty-history-mark">—</span><span>Belum ada asesmen. Pilih perangkat di atas untuk memulai.</span></div>
        @else
            <div class="activity-list">
                @foreach ($recentAssessments as $assessment)
                    <a class="activity-row" href="{{ $assessment->result ? route('assessments.result', $assessment) : route('assessments.show', $assessment) }}">
                        <span class="activity-device-mark {{ $assessment->deviceType->platform === 'ios' ? 'mark-ios' : '' }}">{{ $assessment->deviceType->platform === 'ios' ? 'i' : 'A' }}</span>
                        <span class="activity-main"><strong>{{ data_get($assessment->device_details, 'label') ?: $assessment->deviceType->name }}</strong><small>{{ $assessment->created_at->format('d M Y · H:i') }}</small></span>
                        @if ($assessment->result)
                            <span class="result-pill result-{{ $assessment->result->classification }}">{{ str_replace('_', ' ', $assessment->result->classification) }}</span>
                        @else
                            <span class="result-pill result-pending">Belum selesai</span>
                        @endif
                        <span class="activity-arrow" aria-hidden="true">↗</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection