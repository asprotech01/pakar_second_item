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
            <h1>{{ $assessmentSession->device?->name ?: (data_get($assessmentSession->device_details, 'label') ?: $assessmentSession->deviceType->name) }}</h1>
            <p class="intro-copy">
                @if ($assessmentSession->device)
                    {{ $assessmentSession->device->manufacturer_name ?: 'Merek tidak tersedia' }} <span class="copy-dot">·</span>
                @endif
                {{ $assessmentSession->deviceType->name }} <span class="copy-dot">·</span> {{ $result->evaluated_at?->format('d M Y, H:i') }}
            </p>
            @if ($assessmentSession->storage_variant)
                <p class="intro-copy"><strong>Storage:</strong> {{ $assessmentSession->storage_variant }}</p>
            @endif
            @if ($assessmentSession->device?->image_url && filter_var($assessmentSession->device->image_url, FILTER_VALIDATE_URL) && in_array(parse_url($assessmentSession->device->image_url, PHP_URL_SCHEME), ['http', 'https'], true))
                <img class="assessment-device-image" src="{{ $assessmentSession->device->image_url }}" alt="Gambar {{ $assessmentSession->device->name }}" loading="lazy">
            @endif
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
            <p>Confidence diagnosis memakai CF dari rule terkuat yang mendukung kesimpulan, bukan penjumlahan semua temuan.</p>
            @if ($result->combined_support_score !== null)
                <p>Dukungan gabungan rule untuk diagnosis ini: {{ number_format($result->combined_support_score, 2) }}% (CF gabungan, bukan probabilitas).</p>
            @endif
            <p>Ambang minimum kelulusan (pengaturan tipe perangkat): {{ number_format(($result->confidence_threshold ?? 0) * 100, 0) }}%. Nilai ini batas pembanding, bukan hasil perhitungan.</p>
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
                    <div class="unverified-row"><span class="unknown-badge">?</span><span><strong>{{ $item['question'] }}</strong><small>{{ match ($item['reason']) { 'not_answered' => 'Belum dijawab', 'not_tested' => 'Belum diperiksa', 'source_unknown' => 'Sumber bukti belum diketahui', default => 'Kondisi belum diketahui' } }}</small></span><span class="source-tag">{{ str_replace('_', ' ', $item['source']) }}</span></div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="report-grid">
        <section class="report-section rules-section">
            <div class="section-heading"><div><p class="eyebrow">JEJAK INFERENSI <span class="eyebrow-rule"></span> {{ count($result->active_rules ?? []) }}</p><h2>Rules yang aktif</h2></div></div>
            @forelse ($result->active_rules ?? [] as $index => $rule)
                <article class="rule-row"><span class="rule-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $rule['name'] }}</h3><p>{{ $rule['description'] }}</p><small>{{ $rule['code'] }} <span>→</span> {{ $rule['conclusion'] }}</small>@if (! empty($rule['experts']))<small class="rule-experts">CF ahli: {{ implode(', ', array_column($rule['experts'], 'name')) }}</small>@endif</div><span class="rule-cf">{{ number_format($rule['certainty_factor'] * 100, 0) }}%<small>CF RULE</small></span></article>
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

    @if ($repairCostEstimates['has_issues'])
        <section class="report-section repair-estimate-section">
            <div class="section-heading"><div><p class="eyebrow">Estimasi perbaikan <span class="eyebrow-rule"></span> {{ $repairCostEstimates['entries']->count() }}</p><h2>Komponen yang perlu ditangani</h2></div></div>
            <div class="estimate-list">
                @foreach ($repairCostEstimates['entries'] as $estimate)
                    <article class="recommendation-item estimate-item">
                        <span class="recommendation-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h3>{{ $estimate->issue_name }}</h3>
                            <p>{{ $estimate->description }}</p>
                        </div>
                        <span class="estimate-price">Rp{{ number_format((float) $estimate->minimum_cost, 0, ',', '.') }} – Rp{{ number_format((float) $estimate->maximum_cost, 0, ',', '.') }}</span>
                    </article>
                @endforeach
            </div>
            @if ($repairCostEstimates['has_unavailable'])
                <p>Estimasi biaya perbaikan belum tersedia</p>
            @endif
        </section>
    @endif

    <section class="report-section purchase-cost-section">
        <div class="section-heading"><div><p class="eyebrow">RINGKASAN BIAYA <span class="eyebrow-rule"></span></p><h2>Perkiraan biaya perangkat</h2></div></div>
        <div class="purchase-cost-grid">
            <article class="purchase-cost-card">
                <span>Harga Beli</span>
                @if ($purchaseEvaluation['has_purchase_price'])
                    <strong>Rp {{ number_format((float) $purchaseEvaluation['purchase_price'], 2, ',', '.') }}</strong>
                @else
                    <p>Harga beli belum tersedia.</p>
                @endif
            </article>
            <article class="purchase-cost-card">
                <span>Estimasi Total Perbaikan</span>
                @if ($purchaseEvaluation['has_repair_estimate'])
                    <strong>Rp {{ number_format((float) $purchaseEvaluation['repair_cost_min'], 2, ',', '.') }} – Rp {{ number_format((float) $purchaseEvaluation['repair_cost_max'], 2, ',', '.') }}</strong>
                    @if ($purchaseEvaluation['repair_estimate_status'] === 'no_repairable_issues')
                        <p>Tidak ada kerusakan berbiaya teridentifikasi.</p>
                    @endif
                @else
                    <p>Estimasi biaya perbaikan belum tersedia.</p>
                @endif
            </article>
            <article class="purchase-cost-card purchase-cost-total">
                <span>Total Biaya Efektif</span>
                @if ($purchaseEvaluation['effective_cost_min'] !== null)
                    <strong>Rp {{ number_format((float) $purchaseEvaluation['effective_cost_min'], 2, ',', '.') }} – Rp {{ number_format((float) $purchaseEvaluation['effective_cost_max'], 2, ',', '.') }}</strong>
                @elseif (! $purchaseEvaluation['has_purchase_price'])
                    <p>Total biaya efektif belum dapat dihitung karena harga beli belum tersedia.</p>
                @else
                    <p>Total biaya efektif belum dapat dihitung karena estimasi biaya perbaikan belum tersedia.</p>
                @endif
            </article>
        </div>
    </section>
    <section class="report-section market-value-section">
        <div class="section-heading"><div><p class="eyebrow">DATA PEMBANDING <span class="eyebrow-rule"></span></p><h2>Nilai Pasar</h2></div></div>
        @if ($marketValue !== null)
            <article class="purchase-cost-card market-value-card">
                <strong>Rp {{ number_format((float) $marketValue->market_price_min, 2, ',', '.') }} – Rp {{ number_format((float) $marketValue->market_price_max, 2, ',', '.') }}</strong>
                @if ($marketValue->storage_variant || $marketValue->condition)
                    <p>{{ collect([$marketValue->storage_variant, $marketValue->condition])->filter()->implode(' · ') }}</p>
                @endif
                @if ($marketValue->source)
                    <p>Sumber: {{ $marketValue->source }}</p>
                @endif
                @if ($marketValue->observed_at)
                    <p>Diamati: {{ $marketValue->observed_at->format('d M Y') }}</p>
                @endif
            </article>
        @else
            @if ($assessmentSession->device && $assessmentSession->storage_variant)
                <p>Belum tersedia untuk varian {{ $assessmentSession->storage_variant }}.</p>
                @php
                    $availableMarketValues = $assessmentSession->device->marketValues
                        ->whereNotNull('storage_variant')
                        ->sortBy('storage_variant')
                        ->values();
                @endphp
                @if ($availableMarketValues->isNotEmpty())
                    <p>Varian yang tersedia:</p>
                    <div class="purchase-cost-grid">
                        @foreach ($availableMarketValues as $availableMarketValue)
                            <article class="purchase-cost-card market-value-card">
                                <strong>{{ $availableMarketValue->storage_variant }}</strong>
                                <p>Rp {{ number_format((float) $availableMarketValue->market_price_min, 2, ',', '.') }} – Rp {{ number_format((float) $availableMarketValue->market_price_max, 2, ',', '.') }}</p>
                                @if ($availableMarketValue->condition)
                                    <p>{{ $availableMarketValue->condition }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p>Nilai pasar belum tersedia</p>
                @endif
            @else
                <p>Nilai pasar belum tersedia</p>
            @endif
        @endif
    </section>

    <section class="purchase-evaluation-panel">
        <div class="purchase-evaluation-heading">
            <div>
                <p class="eyebrow">LAPISAN KEPUTUSAN EKONOMI</p>
                <h2>Evaluasi Pembelian</h2>
            </div>
            @if ($purchaseRecommendation['recommendation'] !== null)
                <span class="purchase-evaluation-status">{{ $purchaseRecommendation['recommendation'] }}</span>
            @endif
        </div>
        <div class="purchase-evaluation-details">
            <p><strong>Diagnosis teknis:</strong> {{ $classificationLabels[$purchaseRecommendation['diagnosis']] ?? str_replace('_', ' ', $purchaseRecommendation['diagnosis'] ?? 'Belum diketahui') }}</p>
            @if ($purchaseRecommendation['certainty_factor'] !== null)
                <p><strong>CF diagnosis:</strong> {{ number_format($purchaseRecommendation['certainty_factor'], 0) }}% <span class="purchase-evaluation-note">(keyakinan diagnosis, bukan probabilitas kelayakan pembelian)</span></p>
            @endif
            @if (! $purchaseRecommendation['economic_available'])
                <p class="purchase-evaluation-reason">Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.</p>
                @if ($purchaseRecommendation['recommendation'] !== null)
                    <p>{{ $purchaseRecommendation['reason'] }}</p>
                @endif
            @else
                <p class="purchase-evaluation-reason">{{ $purchaseRecommendation['reason'] }}</p>
            @endif
        </div>
    </section>
    @php
        $purchaseSuggestion = match ($purchaseRecommendation['recommendation']) {
            'LAYAK' => [
                'title' => 'DIREKOMENDASIKAN UNTUK DIBELI',
                'reason' => 'Berdasarkan evaluasi sistem, total biaya efektif lebih baik daripada nilai pasar. '.$purchaseRecommendation['reason'],
            ],
            'BERSYARAT' => $purchaseRecommendation['economic_available']
                ? [
                    'title' => 'PERTIMBANGKAN SEBELUM MEMBELI',
                    'reason' => $purchaseRecommendation['reason'],
                ]
                : [
                    'title' => 'LENGKAPI INFORMASI SEBELUM MEMBELI',
                    'reason' => 'Nilai pasar atau harga belum cukup lengkap untuk melakukan perbandingan ekonomi.',
                ],
            'TIDAK LAYAK' => [
                'title' => 'SEBAIKNYA TIDAK DIBELI',
                'reason' => $purchaseRecommendation['reason'],
            ],
            default => null,
        };
    @endphp
    @if ($purchaseSuggestion !== null)
        <section class="purchase-evaluation-panel">
            <div class="purchase-evaluation-heading">
                <div>
                    <p class="eyebrow">SARAN BERDASARKAN EVALUASI SISTEM</p>
                    <h2>Saran Pembelian</h2>
                </div>
            </div>
            <div class="purchase-evaluation-details">
                <p class="purchase-evaluation-status">{{ $purchaseSuggestion['title'] }}</p>
                <p class="purchase-evaluation-reason">{{ $purchaseSuggestion['reason'] }}</p>
            </div>
        </section>
    @endif
</div>
@endsection