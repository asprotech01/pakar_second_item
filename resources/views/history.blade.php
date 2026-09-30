@extends('layouts.app')

@section('title', 'Riwayat asesmen | Tilik')

@section('content')
<div class="page-wrap history-page">
    <div class="breadcrumbs"><a href="{{ route('dashboard') }}">Ruang kerja</a><span>/</span><span>Riwayat</span></div>
    <section class="history-heading"><div><p class="eyebrow">ARSIP EVALUASI <span class="eyebrow-rule"></span> DATASET</p><h1>Riwayat asesmen</h1><p class="intro-copy">Setiap pemeriksaan dan hasil yang tercatat.</p></div><a class="button button-dark" href="{{ route('dashboard') }}#pilih-perangkat">+ Asesmen baru</a></section>
    <div class="history-table-wrap">
        <div class="history-table-head"><span>PERANGKAT</span><span>WAKTU</span><span>COMPLETENESS</span><span>CONFIDENCE</span><span>HASIL</span><span></span></div>
        @forelse ($assessments as $assessment)
            <a class="history-table-row" href="{{ $assessment->result ? route('assessments.result', $assessment) : route('assessments.show', $assessment) }}">
                <span class="history-device"><span class="activity-device-mark {{ $assessment->deviceType->platform === 'ios' ? 'mark-ios' : '' }}">{{ $assessment->deviceType->platform === 'ios' ? 'i' : 'A' }}</span><strong>{{ data_get($assessment->device_details, 'label') ?: $assessment->deviceType->name }}</strong></span>
                <span>{{ $assessment->created_at->format('d M Y · H:i') }}</span>
                <span>{{ $assessment->result ? number_format($assessment->result->data_completeness, 0).'%' : '—' }}</span>
                <span>{{ $assessment->result ? number_format($assessment->result->confidence_score, 0).'%' : '—' }}</span>
                <span>@if ($assessment->result)<span class="result-pill result-{{ $assessment->result->classification }}">{{ str_replace('_', ' ', $assessment->result->classification) }}</span>@else<span class="result-pill result-pending">Berjalan</span>@endif</span>
                <span class="activity-arrow" aria-hidden="true">↗</span>
            </a>
        @empty
            <div class="empty-history"><span class="empty-history-mark">—</span><span>Riwayat masih kosong. Asesmen pertama akan muncul di sini.</span></div>
        @endforelse
    </div>
    <div class="pagination-wrap">{{ $assessments->links() }}</div>
</div>
@endsection