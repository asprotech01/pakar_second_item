@extends('layouts.app')

@section('title', 'Inspeksi '.$assessmentSession->deviceType->name.' | Tilik')

@section('content')
<div class="page-wrap inspection-page">
    <div class="breadcrumbs"><a href="{{ route('dashboard') }}">Ruang kerja</a><span>/</span><span>Inspeksi perangkat</span></div>
    <section class="inspection-heading">
        <div>
            <p class="eyebrow">{{ strtoupper($assessmentSession->deviceType->platform) }} <span class="eyebrow-rule"></span> ASESMEN #{{ str_pad((string) $assessmentSession->id, 5, '0', STR_PAD_LEFT) }}</p>
            <h1>{{ data_get($assessmentSession->device_details, 'label') ?: $assessmentSession->deviceType->name }}</h1>
            <p class="intro-copy">Jawab berdasarkan bukti yang tersedia. Pilih “Belum diketahui” bila kondisi belum dapat dipastikan.</p>
        </div>
        <div class="inspection-total"><strong>{{ $questions->count() }}</strong><span>PEMERIKSAAN</span></div>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('assessments.evaluate', $assessmentSession) }}" data-inspection-form>
        @csrf
        <div class="inspection-layout">
            <aside class="inspection-rail">
                <div class="progress-copy"><span data-step-count>01</span><span>/ {{ str_pad((string) $questions->count(), 2, '0', STR_PAD_LEFT) }}</span></div>
                <div class="progress-track"><span data-progress-bar></span></div>
                <p class="rail-label">BAGIAN PEMERIKSAAN</p>
                <ol class="category-list">
                    @foreach ($categories as $categoryName => $categoryQuestions)
                        <li data-category-index="{{ $loop->index }}" class="{{ $loop->first ? 'is-current' : '' }}"><span class="category-index">0{{ $loop->iteration }}</span><span>{{ $categoryName }}</span><small>{{ str_pad((string) $categoryQuestions->count(), 2, '0', STR_PAD_LEFT) }}</small></li>
                    @endforeach
                </ol>
                <figure class="inspection-reference">
                    <img src="{{ asset('images/phone-device-detail.jpg') }}" alt="Referensi visual kondisi fisik beberapa smartphone">
                    <figcaption><span>REFERENSI VISUAL</span><small>Periksa bodi dari beberapa sudut.</small></figcaption>
                </figure>
                <div class="unknown-note"><span class="unknown-note-icon">?</span><p><strong>Belum tahu itu jawaban.</strong><br>Data UNKNOWN akan ditampilkan sebagai hal yang perlu diverifikasi, bukan dianggap normal.</p></div>
            </aside>

            <div class="question-column">
                @foreach ($questions as $index => $question)
                    @php
                        $existing = $answers->get($question->id);
                        $existingSelection = $existing?->answer_state === 'UNKNOWN'
                            ? 'unknown'
                            : ($existing?->question_option_id ? 'option:'.$existing->question_option_id : null);
                    @endphp
                    <section class="question-step" data-question-step data-category="{{ $question->inspectionCategory->name }}">
                        <div class="question-kicker"><span>PEMERIKSAAN {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><span>{{ $question->inspectionCategory->name }}</span></div>
                        <h2>{{ $question->prompt }}</h2>
                        @if ($question->code === 'activation_lock')
                            <p class="question-hint">Pastikan akun pemilik sebelumnya sudah dilepas. Perangkat terkunci sebaiknya tidak ditransaksikan sebelum statusnya jelas.</p>
                        @endif
                        <fieldset class="answer-options">
                            <legend class="sr-only">Pilih kondisi</legend>
                            @foreach ($question->options as $option)
                                <label class="answer-option">
                                    <input type="radio" name="answers[{{ $question->id }}][selection]" value="option:{{ $option->id }}" {{ $existingSelection === 'option:'.$option->id ? 'checked' : '' }}>
                                    <span class="choice-indicator"></span>
                                    <span class="answer-option-copy"><strong>{{ $option->label }}</strong><small>{{ $option->value === 'normal' ? 'Tidak ditemukan masalah pada pemeriksaan ini' : 'Perlu perhatian atau pemeriksaan lebih lanjut' }}</small></span>
                                    <span class="choice-tag {{ $option->value === 'normal' ? 'tag-good' : 'tag-caution' }}">{{ $option->value === 'normal' ? 'NORMAL' : 'TEMUAN' }}</span>
                                </label>
                            @endforeach
                            <label class="answer-option answer-unknown">
                                <input type="radio" name="answers[{{ $question->id }}][selection]" value="unknown" {{ $existingSelection === 'unknown' ? 'checked' : '' }}>
                                <span class="choice-indicator"></span>
                                <span class="answer-option-copy"><strong>Belum diketahui</strong><small>Belum diperiksa atau bukti belum cukup</small></span>
                                <span class="choice-tag tag-unknown">UNKNOWN</span>
                            </label>
                        </fieldset>
                        <div class="evidence-fields" data-evidence-fields>
                            <label class="source-select"><span>Sumber bukti</span>
                                <select name="answers[{{ $question->id }}][source]">
                                    @php
                                        $sourceLabels = [
                                            'DIRECT_INSPECTION' => 'Pemeriksaan langsung',
                                            'SELLER' => 'Informasi penjual',
                                            'SYSTEM_INFORMATION' => 'Informasi sistem',
                                            'DOCUMENT' => 'Dokumen',
                                            'USER_ESTIMATION' => 'Perkiraan saya',
                                            'UNKNOWN' => 'Sumber belum diketahui',
                                        ];
                                    @endphp
                                    @foreach ($sourceLabels as $sourceValue => $sourceLabel)
                                        <option value="{{ $sourceValue }}" {{ ($existing?->evidence_source ?? 'DIRECT_INSPECTION') === $sourceValue ? 'selected' : '' }}>{{ $sourceLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="notes-field"><span>Catatan <small>opsional</small></span><input name="answers[{{ $question->id }}][notes]" type="text" maxlength="1000" value="{{ $existing?->notes }}" placeholder="Contoh: ada baret tipis di sudut kanan"></label>
                        </div>
                    </section>
                @endforeach
                <div class="inspection-controls" data-inspection-controls>
                    <button class="button button-quiet" type="button" data-previous disabled><span aria-hidden="true">←</span> Sebelumnya</button>
                    <div class="control-spacer"></div>
                    <button class="button button-dark" type="button" data-next>Berikutnya <span aria-hidden="true">→</span></button>
                    <button class="button button-accent" type="submit" data-submit-evaluation>Evaluasi perangkat <span aria-hidden="true">↗</span></button>
                </div>
                <noscript><p class="noscript-note">JavaScript tidak aktif. Semua pertanyaan ditampilkan berurutan; isi pilihan lalu tekan Evaluasi perangkat.</p></noscript>
            </div>
        </div>
    </form>
</div>
@endsection