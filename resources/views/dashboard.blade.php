@extends('layouts.app')

@section('title', 'Ruang kerja | Tilik')

@section('content')
<div class="page-wrap dashboard-page">
    @if ($errors->any())
        <div class="form-errors" role="alert">{{ $errors->first() }}</div>
    @endif
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
            <div><p class="eyebrow">MULAI BARU <span class="eyebrow-rule"></span> 01</p><h2>Pilih perangkat</h2></div>
            <span class="section-aside">Estimasi 3–5 menit · Simpan hasil otomatis</span>
        </div>
        <form
            class="workspace-assessment-form"
            method="POST"
            action="{{ route('assessments.store') }}"
            data-workspace-assessment
            data-autocomplete-url="{{ route('devices.autocomplete') }}"
            data-initial-device="{{ $selectedDevice ? json_encode([
                'id' => $selectedDevice->id,
                'name' => $selectedDevice->name,
                'manufacturer_name' => $selectedDevice->manufacturer_name,
                'storage_variants' => $selectedDevice->storageVariants(),
            ]) : '' }}"
            data-initial-storage="{{ old('storage_variant') }}"
        >
            @csrf
            <input type="hidden" name="workspace" value="1">
            <input type="hidden" name="device_id" value="{{ old('device_id', $selectedDevice?->id) }}" data-device-id>

            <div class="workspace-device-search">
                <label for="workspace-device-search">Perangkat</label>
                <input
                    id="workspace-device-search"
                    type="search"
                    name="device_search"
                    value="{{ old('device_search', $selectedDevice?->name) }}"
                    maxlength="100"
                    placeholder="Cari nama atau merek, misalnya Vivo V30"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="workspace-device-results"
                    required
                    data-device-search
                >
                <div id="workspace-device-results" class="workspace-device-results" role="listbox" hidden data-device-results></div>
                <p class="workspace-search-status" role="status" aria-live="polite" data-search-status>Perangkat dicari dari katalog lokal yang sudah tersimpan.</p>
                <div class="workspace-selected-device" @if (! $selectedDevice) hidden @endif data-selected-device>
                    <strong data-selected-name>{{ $selectedDevice?->name }}</strong>
                    <span data-selected-manufacturer>{{ $selectedDevice?->manufacturer_name }}</span>
                    <span data-selected-storage></span>
                </div>
                @error('device_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="workspace-form-fields">
                <label class="workspace-field">
                    <span>Profil pemeriksaan</span>
                    <select name="device_type_id" required>
                        <option value="">Pilih profil perangkat</option>
                        @foreach ($deviceTypes as $deviceType)
                            <option value="{{ $deviceType->id }}" @selected(old('device_type_id') == $deviceType->id)>
                                {{ $deviceType->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('device_type_id') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="workspace-field" hidden data-storage-field>
                    <span>Storage</span>
                    <select name="storage_variant" disabled data-storage-select>
                        <option value="">Pilih storage</option>
                    </select>
                    @error('storage_variant') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="workspace-field">
                    <span>Harga beli <small>opsional</small></span>
                    <input
                        type="number"
                        name="purchase_price"
                        value="{{ old('purchase_price') }}"
                        min="0.01"
                        max="9999999999999.99"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Contoh: 4000000"
                    >
                    @error('purchase_price') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>

            <button class="button button-dark workspace-submit" type="submit">Mulai assessment <span aria-hidden="true">→</span></button>
        </form>
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