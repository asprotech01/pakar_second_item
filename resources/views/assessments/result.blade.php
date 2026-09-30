@extends('layouts.app')

@php
    $result = $assessmentSession->result;
    $classificationLabels = [
        'layak' => 'Layak dipertimbangkan',
        'bersyarat' => 'Perlu pemeriksaan lanjutan',
        'tidak_direkomendasikan' => 'Tidak direkomendasikan',
        'unverified' => 'Belum cukup terverifikasi',
    ];
    $riskLabels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis', 'unknown' => 'Belum diketahui'];
@endphp

@section('title', 'Hasil asesmen | Tilik')

@section('content')
<div class="page-wrap result-page">
    <div class="breadcrumbs"><a href="{{ route('dashboard') }}">Ruang kerja</a><span>/</span><a href="{{ route('assessments.history') }}">Riwayat</a><span>/</span><span>Hasil asesmen</span></div>
    <section class="result-header">
        <div>
            <p class="eyebrow">HASIL EVALUASI <span class="eyebrow-rule"></span> #{{ str_pad((string) $assessmentSession->id, 5, '0', STR_PAD_LEFT) }}</p>
            <h1>{{ data_get($assessmentSession->device_details, 'label') ?: $assessmentSession->deviceType->name }}</h1>
            <p class="intro-copy">{{ $assessmentSession->deviceType->name }} <span class="copy-dot">·</span> {{ $result->evaluated_at?->format('d M Y, H:i') }}</p>
        </div>
        <a class="button button-quiet" href="{{ route('dashboard') }}">← Ruang kerja</a>
    </section>

    <section class="verdict-band verdict-{{ $result->classification }}">
        <div class="verdict-symbol" aria-hidden="true">{{ $result->classification === 'layak' ? '✓' : ($result->classification === 'unverified' ? '?' : '!') }}</div>
        <div class="verdict-copy"><p class="eyebrow">KESIMPULAN SISTEM</p><h2>{{ $classificationLabels[$result->classification] ?? str_replace('_', ' ', $result->classification) }}</h2>
            <p>{{ $result->threshold_met ? 'Ambang keyakinan dan kelengkapan data terpenuhi.' : 'Kesimpulan belum melewati ambang keyakinan atau kelengkapan data.' }}</p>
        </div>
        <div class="verdict-risk"><span>TINGKAT RISIKO</span><strong>{{ $riskLabels[$result->risk_level] ?? $result->risk_level }}</strong></div>
    </section>

    <section class="result-metrics">
        <article class="score-panel score-confidence">
            <div class="score-heading"><span>CONFIDENCE</span><span class="score-mark">CF</span></div>
            <strong class="score-number">{{ number_format($result->confidence_score, 0) }}<small>%</small></strong>
            <div class="score-track"><span style="width: {{ min(100, max(0, $result->confidence_score)) }}%"></span></div>
            <p>Keyakinan sistem atas kesimpulan terpilih. Ambang perangkat: {{ number_format(($result->confidence_threshold ?? 0) * 100, 0) }}%.</p>
        </article>
        <article class="score-panel score-completeness">
            <div class="score-heading"><span>DATA COMPLETENESS</span><span class="score-mark score-mark-orange">DATA</span></div>
            <strong class="score-number">{{ number_format($result->data_completeness, 0) }}<small>%</small></strong>
            <div class="score-track"><span style="width: {{ min(100, max(0, $result->data_completeness)) }}%"></span></div>
            <p>Kelengkapan pemeriksaan wajib. Ambang perangkat: {{ number_format(($result->completeness_threshold ?? 0) * 100, 0) }}%.</p>
        </article>
    </section>

    @if (! empty($result->unverified_data))
        <section class="report-section unverified-section">
            <div class="section-heading"><div><p class="eyebrow">PERLU DIVERIFIKASI <span class="eyebrow-rule"></span> {{ count($result->unverified_data) }}</p><h2>Data belum terverifikasi</h2></div></div>
            <div class="unverified-list">
                @foreach ($result->unverified_data as $item)
                    <div class="unverified-row"><span class="unknown-badge">?</span><span><strong>{{ $item['question'] }}</strong><small>{{ $item['reason'] === 'not_answered' ? 'Belum dijawab' : ($item['reason'] === 'source_unknown' ? 'Sumber bukti belum diketahui' : 'Kondisi belum diketahui') }}</small></span><span class="source-tag">{{ str_replace('_', ' ', $item['source']) }}</span></div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="report-grid">
        <section class="report-section rules-section">
            <div class="section-heading"><div><p class="eyebrow">JEJAK INFERENSI <span class="eyebrow-rule"></span> {{ count($result->active_rules ?? []) }}</p><h2>Rules yang aktif</h2></div></div>
            @forelse ($result->active_rules ?? [] as $index => $rule)
                <article class="rule-row"><span class="rule-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $rule['name'] }}</h3><p>{{ $rule['description'] }}</p><small>{{ $rule['code'] }} <span>→</span> {{ $rule['conclusion'] }}</small></div><span class="rule-cf">{{ number_format($rule['certainty_factor'] * 100, 0) }}%<small>CF RULE</small></span></article>
            @empty
                <div class="empty-report">Tidak ada rule kesimpulan yang aktif. Lengkapi pemeriksaan yang belum diketahui untuk memperoleh penilaian.</div>
            @endforelse
        </section>

        <section class="report-section factors-section">
            <div class="section-heading"><div><p class="eyebrow">BUKTI PEMERIKSAAN <span class="eyebrow-rule"></span> {{ count($result->contributing_factors ?? []) }}</p><h2>Faktor penyebab</h2></div></div>
            @forelse ($result->contributing_factors ?? [] as $factor)
                <div class="factor-row"><span class="factor-dot {{ ($factor['certainty_factor'] ?? 0) >= 0.7 ? 'factor-strong' : '' }}"></span><span class="factor-copy"><strong>{{ $factor['fact'] ?? $factor['question'] ?? $factor['fact_code'] }}</strong><small>{{ $factor['answer'] ?? str_replace('_', ' ', $factor['source'] ?? '') }}</small></span><span class="factor-cf">{{ number_format(($factor['certainty_factor'] ?? 0) * 100, 0) }}%</span></div>
            @empty
                <div class="empty-report">Belum ada bukti yang dapat berkontribusi pada kesimpulan.</div>
            @endforelse
        </section>
    </div>

    @if (! empty($result->recommendations))
        <section class="report-section recommendation-section">
            <div class="section-heading"><div><p class="eyebrow">LANGKAH BERIKUTNYA <span class="eyebrow-rule"></span> {{ count($result->recommendations) }}</p><h2>Saran pemeriksaan</h2></div></div>
            <div class="recommendation-grid">
                @foreach ($result->recommendations as $recommendation)
                    <article class="recommendation-item"><span class="recommendation-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $recommendation['title'] }}</h3><p>{{ $recommendation['body'] }}</p></div></article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection