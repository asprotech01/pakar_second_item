@extends('layouts.app')

@section('title', 'Basis Pengetahuan | Tilik')

@section('content')
<div class="page-wrap result-page">
    <section class="result-header">
        <div>
            <p class="eyebrow">ADMINISTRASI</p>
            <h1>Basis Pengetahuan</h1>
            <p class="intro-copy">Kelola fakta, rule, dan kondisi penilaian teknis untuk mesin inferensi.</p>
        </div>
        <a class="button button-quiet" href="{{ route('dashboard') }}">← Ruang kerja</a>
    </section>

    @if (session('status'))
        <section class="report-section">
            <div class="empty-report" style="margin-bottom: 0;">
                {{ session('status') }}
            </div>
        </section>
    @endif

    <section class="report-grid" style="margin-top: 1rem;">
        <article class="report-section">
            <p class="eyebrow">RINGKASAN</p>
            <h2>{{ $stats['facts_total'] }}</h2>
            <p>Fakta tercatat</p>
        </article>
        <article class="report-section">
            <p class="eyebrow">RINGKASAN</p>
            <h2>{{ $stats['rules_total'] }}</h2>
            <p>Rule aktif &amp; tersimpan</p>
        </article>
        <article class="report-section">
            <p class="eyebrow">VALIDASI</p>
            <h2>{{ $stats['rules_validated'] }}</h2>
            <p>Rule dengan validasi ahli</p>
        </article>
        <article class="report-section">
            <p class="eyebrow">MENUNGGU</p>
            <h2>{{ $stats['rules_pending_validation'] }}</h2>
            <p>Rule belum divalidasi</p>
        </article>
    </section>

    <div class="report-grid">
        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Fakta</p>
                    <h2>Tambah fakta baru</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.knowledge-base.facts.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Kode
                        <input type="text" name="code" value="{{ old('code') }}" required>
                    </label>
                    <label>
                        Nama
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Klasifikasi
                        <select name="classification">
                            <option value="">-- Tidak ada --</option>
                            <option value="layak" {{ old('classification') === 'layak' ? 'selected' : '' }}>layak</option>
                            <option value="bersyarat" {{ old('classification') === 'bersyarat' ? 'selected' : '' }}>bersyarat</option>
                            <option value="tidak_direkomendasikan" {{ old('classification') === 'tidak_direkomendasikan' ? 'selected' : '' }}>tidak_direkomendasikan</option>
                            <option value="unverified" {{ old('classification') === 'unverified' ? 'selected' : '' }}>unverified</option>
                        </select>
                    </label>
                    <label>
                        Tingkat risiko
                        <select name="risk_level">
                            <option value="">-- Tidak ada --</option>
                            <option value="low" {{ old('risk_level') === 'low' ? 'selected' : '' }}>low</option>
                            <option value="medium" {{ old('risk_level') === 'medium' ? 'selected' : '' }}>medium</option>
                            <option value="high" {{ old('risk_level') === 'high' ? 'selected' : '' }}>high</option>
                            <option value="critical" {{ old('risk_level') === 'critical' ? 'selected' : '' }}>critical</option>
                            <option value="unknown" {{ old('risk_level') === 'unknown' ? 'selected' : '' }}>unknown</option>
                        </select>
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_conclusion" value="1" {{ old('is_conclusion') ? 'checked' : '' }}>
                        Ini adalah kesimpulan
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif
                    </label>
                </div>
                <label>
                    Deskripsi
                    <textarea name="description" rows="3">{{ old('description') }}</textarea>
                </label>
                <button class="button" type="submit">Simpan fakta</button>
            </form>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Rule</p>
                    <h2>Tambah rule baru</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.knowledge-base.rules.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Kode rule
                        <input type="text" name="code" value="{{ old('code') }}" required>
                    </label>
                    <label>
                        Nama rule
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Kesimpulan
                        <select name="conclusion_fact_id" required>
                            <option value="">-- Pilih kesimpulan --</option>
                            @foreach ($facts as $fact)
                                <option value="{{ $fact->id }}" {{ old('conclusion_fact_id') == $fact->id ? 'selected' : '' }}>{{ $fact->name }} ({{ $fact->code }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Kondisi trigger
                        <select name="condition_fact_id" required>
                            <option value="">-- Pilih kondisi --</option>
                            @foreach ($facts as $fact)
                                <option value="{{ $fact->id }}" {{ old('condition_fact_id') == $fact->id ? 'selected' : '' }}>{{ $fact->name }} ({{ $fact->code }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        CF
                        <input type="number" step="0.01" min="0" max="1" name="certainty_factor" value="{{ old('certainty_factor', '0.85') }}" required>
                    </label>
                    <label>
                        Prioritas
                        <input type="number" min="0" max="1000" name="priority" value="{{ old('priority', 0) }}">
                    </label>
                    <label>
                        Klasifikasi
                        <select name="classification">
                            <option value="">-- Tidak ada --</option>
                            <option value="layak" {{ old('classification') === 'layak' ? 'selected' : '' }}>layak</option>
                            <option value="bersyarat" {{ old('classification') === 'bersyarat' ? 'selected' : '' }}>bersyarat</option>
                            <option value="tidak_direkomendasikan" {{ old('classification') === 'tidak_direkomendasikan' ? 'selected' : '' }}>tidak_direkomendasikan</option>
                            <option value="unverified" {{ old('classification') === 'unverified' ? 'selected' : '' }}>unverified</option>
                        </select>
                    </label>
                    <label>
                        Tingkat risiko
                        <select name="risk_level">
                            <option value="">-- Tidak ada --</option>
                            <option value="low" {{ old('risk_level') === 'low' ? 'selected' : '' }}>low</option>
                            <option value="medium" {{ old('risk_level') === 'medium' ? 'selected' : '' }}>medium</option>
                            <option value="high" {{ old('risk_level') === 'high' ? 'selected' : '' }}>high</option>
                            <option value="critical" {{ old('risk_level') === 'critical' ? 'selected' : '' }}>critical</option>
                            <option value="unknown" {{ old('risk_level') === 'unknown' ? 'selected' : '' }}>unknown</option>
                        </select>
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Rule aktif
                    </label>
                </div>
                <label>
                    Deskripsi rule
                    <textarea name="description" rows="3">{{ old('description') }}</textarea>
                </label>
                <button class="button" type="submit">Simpan rule</button>
            </form>
        </section>
    </div>

    <div class="report-grid">
        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Panel Ahli</p>
                    <h2>Tambah ahli baru</h2>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.knowledge-base.experts.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Nama ahli
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Email
                        <input type="email" name="email" value="{{ old('email') }}">
                    </label>
                    <label>
                        Afiliasi
                        <input type="text" name="affiliation" value="{{ old('affiliation') }}">
                    </label>
                    <label>
                        Kredensial
                        <input type="text" name="credentials" value="{{ old('credentials') }}">
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Ahli aktif
                    </label>
                </div>
                <button class="button" type="submit">Simpan ahli</button>
            </form>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Validasi</p>
                    <h2>Validasi rule oleh ahli</h2>
                </div>
            </div>

            @forelse ($rules as $rule)
                <form method="POST" action="{{ route('admin.knowledge-base.rules.validate', ['rule' => $rule->id]) }}" class="inspection-form" style="margin-bottom: 1.25rem;">
                    @csrf
                    <h3>{{ $rule->name }} ({{ $rule->code }})</h3>
                    <div class="form-grid">
                        <label>
                            Ahli
                            <select name="expert_id" required>
                                <option value="">-- Pilih ahli --</option>
                                @foreach ($experts as $expert)
                                    <option value="{{ $expert->id }}">{{ $expert->name }}{{ $expert->credentials ? ' — '.$expert->credentials : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            CF validasi
                            <input type="number" step="0.01" min="0" max="1" name="certainty_factor" value="{{ old('certainty_factor', $rule->certainty_factor) }}" required>
                        </label>
                    </div>
                    <label>
                        Catatan ahli
                        <textarea name="notes" rows="2" placeholder="Catatan validasi ahli...">{{ old('notes') }}</textarea>
                    </label>
                    <button class="button" type="submit">Simpan validasi</button>
                </form>
            @empty
                <p>Belum ada rule yang dapat divalidasi.</p>
            @endforelse
        </section>
    </div>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Ahli yang tersedia</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Kredensial</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($experts as $expert)
                        <tr>
                            <td>{{ $expert->name }}</td>
                            <td>{{ $expert->email ?? '-' }}</td>
                            <td>{{ $expert->credentials ?? '-' }}</td>
                            <td>{{ $expert->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Belum ada ahli yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Fakta yang tersedia</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Risiko</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($facts as $fact)
                        <tr>
                            <td>{{ $fact->code }}</td>
                            <td>{{ $fact->name }}</td>
                            <td>{{ $fact->risk_level ?? '-' }}</td>
                            <td>{{ $fact->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Belum ada fakta yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Rule yang tersedia</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Kesimpulan</th>
                        <th>CF</th>
                        <th>Validasi</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr>
                            <td>{{ $rule->code }}</td>
                            <td>{{ $rule->conclusion?->name ?? '-' }}</td>
                            <td>{{ number_format($rule->certainty_factor, 2) }}</td>
                            <td>{{ $rule->experts->isNotEmpty() ? $rule->experts->map(fn ($expert) => $expert->name)->implode(', ') : 'Belum ada' }}</td>
                            <td>{{ $rule->priority }}</td>
                            <td>{{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Belum ada rule yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="report-grid">
        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Katalog</p>
                    <h2>Kategori perangkat</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-base.device-categories.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Nama kategori
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}">
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif
                    </label>
                </div>
                <label>
                    Deskripsi
                    <textarea name="description" rows="2">{{ old('description') }}</textarea>
                </label>
                <button class="button" type="submit">Simpan kategori perangkat</button>
            </form>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Inspeksi</p>
                    <h2>Kategori pemeriksaan</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-base.inspection-categories.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Nama kategori
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}">
                    </label>
                    <label>
                        Urutan
                        <input type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', 0) }}">
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif
                    </label>
                </div>
                <label>
                    Deskripsi
                    <textarea name="description" rows="2">{{ old('description') }}</textarea>
                </label>
                <button class="button" type="submit">Simpan kategori inspeksi</button>
            </form>
        </section>
    </div>

    <div class="report-grid">
        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Katalog</p>
                    <h2>Tipe perangkat</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-base.device-types.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Kategori
                        <select name="device_category_id" required>
                            <option value="">-- Pilih kategori --</option>
                            @foreach ($deviceCategories as $deviceCategory)
                                <option value="{{ $deviceCategory->id }}" {{ old('device_category_id') == $deviceCategory->id ? 'selected' : '' }}>{{ $deviceCategory->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Nama tipe
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}">
                    </label>
                    <label>
                        Platform
                        <input type="text" name="platform" value="{{ old('platform') }}" required>
                    </label>
                    <label>
                        Confidence threshold
                        <input type="number" step="0.01" min="0" max="1" name="confidence_threshold" value="{{ old('confidence_threshold', 0.6) }}" required>
                    </label>
                    <label>
                        Completeness threshold
                        <input type="number" step="0.01" min="0" max="1" name="completeness_threshold" value="{{ old('completeness_threshold', 0.7) }}" required>
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif
                    </label>
                </div>
                <button class="button" type="submit">Simpan tipe perangkat</button>
            </form>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Kuesioner</p>
                    <h2>Pertanyaan inspeksi</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-base.questions.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Kategori inspeksi
                        <select name="inspection_category_id" required>
                            <option value="">-- Pilih kategori --</option>
                            @foreach ($inspectionCategories as $inspectionCategory)
                                <option value="{{ $inspectionCategory->id }}" {{ old('inspection_category_id') == $inspectionCategory->id ? 'selected' : '' }}>{{ $inspectionCategory->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Tipe perangkat
                        <select name="device_type_id">
                            <option value="">-- Semua tipe --</option>
                            @foreach ($deviceTypes as $deviceType)
                                <option value="{{ $deviceType->id }}" {{ old('device_type_id') == $deviceType->id ? 'selected' : '' }}>{{ $deviceType->name }} ({{ $deviceType->platform }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Kode
                        <input type="text" name="code" value="{{ old('code') }}" required>
                    </label>
                    <label>
                        Jenis input
                        <select name="input_type">
                            <option value="single_choice" {{ old('input_type') === 'single_choice' ? 'selected' : '' }}>single_choice</option>
                            <option value="multiple_choice" {{ old('input_type') === 'multiple_choice' ? 'selected' : '' }}>multiple_choice</option>
                            <option value="boolean" {{ old('input_type') === 'boolean' ? 'selected' : '' }}>boolean</option>
                            <option value="text" {{ old('input_type') === 'text' ? 'selected' : '' }}>text</option>
                            <option value="number" {{ old('input_type') === 'number' ? 'selected' : '' }}>number</option>
                        </select>
                    </label>
                    <label>
                        Bobot kelengkapan
                        <input type="number" step="0.01" min="0" max="100" name="completeness_weight" value="{{ old('completeness_weight', 1) }}" required>
                    </label>
                    <label>
                        Urutan
                        <input type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', 0) }}">
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_required" value="1" checked>
                        Wajib dijawab
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif
                    </label>
                </div>
                <label>
                    Pertanyaan
                    <textarea name="prompt" rows="3" required>{{ old('prompt') }}</textarea>
                </label>
                <button class="button" type="submit">Simpan pertanyaan</button>
            </form>
        </section>
    </div>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Kategori perangkat</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deviceCategories as $deviceCategory)
                        <tr>
                            <td>{{ $deviceCategory->name }}</td>
                            <td>{{ $deviceCategory->slug }}</td>
                            <td>{{ $deviceCategory->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.knowledge-base.device-categories.update', ['deviceCategory' => $deviceCategory->id]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $deviceCategory->name }}" required style="max-width: 120px;">
                                    <input type="text" name="slug" value="{{ $deviceCategory->slug }}" style="max-width: 110px;">
                                    <select name="is_active" style="max-width: 90px;">
                                        <option value="1" {{ $deviceCategory->is_active ? 'selected' : '' }}>Aktif</option>
                                        <option value="0" {{ ! $deviceCategory->is_active ? 'selected' : '' }}>Nonaktif</option>
                                    </select>
                                    <button class="button button-quiet" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.knowledge-base.device-categories.destroy', ['deviceCategory' => $deviceCategory->id]) }}" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button" type="submit" onclick="return confirm('Hapus kategori ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Belum ada kategori perangkat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Kategori inspeksi</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Slug</th>
                        <th>Urutan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inspectionCategories as $inspectionCategory)
                        <tr>
                            <td>{{ $inspectionCategory->name }}</td>
                            <td>{{ $inspectionCategory->slug }}</td>
                            <td>{{ $inspectionCategory->sort_order }}</td>
                            <td>{{ $inspectionCategory->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.knowledge-base.inspection-categories.update', ['inspectionCategory' => $inspectionCategory->id]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $inspectionCategory->name }}" required style="max-width: 120px;">
                                    <input type="text" name="slug" value="{{ $inspectionCategory->slug }}" style="max-width: 110px;">
                                    <input type="number" name="sort_order" value="{{ $inspectionCategory->sort_order }}" min="0" max="1000" style="max-width: 70px;">
                                    <select name="is_active" style="max-width: 90px;">
                                        <option value="1" {{ $inspectionCategory->is_active ? 'selected' : '' }}>Aktif</option>
                                        <option value="0" {{ ! $inspectionCategory->is_active ? 'selected' : '' }}>Nonaktif</option>
                                    </select>
                                    <button class="button button-quiet" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.knowledge-base.inspection-categories.destroy', ['inspectionCategory' => $inspectionCategory->id]) }}" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button" type="submit" onclick="return confirm('Hapus kategori ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Belum ada kategori inspeksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Tipe perangkat</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Platform</th>
                        <th>Threshold</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deviceTypes as $deviceType)
                        <tr>
                            <td>{{ $deviceType->name }}</td>
                            <td>{{ $deviceType->category?->name ?? '-' }}</td>
                            <td>{{ $deviceType->platform }}</td>
                            <td>{{ number_format($deviceType->confidence_threshold, 2) }}/{{ number_format($deviceType->completeness_threshold, 2) }}</td>
                            <td>{{ $deviceType->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.knowledge-base.device-types.update', ['deviceType' => $deviceType->id]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ $deviceType->name }}" required style="max-width: 110px;">
                                    <select name="device_category_id" style="max-width: 120px;">
                                        @foreach ($deviceCategories as $deviceCategory)
                                            <option value="{{ $deviceCategory->id }}" {{ $deviceType->device_category_id == $deviceCategory->id ? 'selected' : '' }}>{{ $deviceCategory->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="platform" value="{{ $deviceType->platform }}" required style="max-width: 90px;">
                                    <input type="number" step="0.01" min="0" max="1" name="confidence_threshold" value="{{ $deviceType->confidence_threshold }}" style="max-width: 75px;">
                                    <input type="number" step="0.01" min="0" max="1" name="completeness_threshold" value="{{ $deviceType->completeness_threshold }}" style="max-width: 75px;">
                                    <select name="is_active" style="max-width: 90px;">
                                        <option value="1" {{ $deviceType->is_active ? 'selected' : '' }}>Aktif</option>
                                        <option value="0" {{ ! $deviceType->is_active ? 'selected' : '' }}>Nonaktif</option>
                                    </select>
                                    <button class="button button-quiet" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.knowledge-base.device-types.destroy', ['deviceType' => $deviceType->id]) }}" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button" type="submit" onclick="return confirm('Hapus tipe ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Belum ada tipe perangkat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Inventaris</p>
                <h2>Pertanyaan inspeksi</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Pertanyaan</th>
                        <th>Kategori</th>
                        <th>Input</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($questions as $question)
                        <tr>
                            <td>{{ $question->code }}</td>
                            <td>{{ $question->prompt }}</td>
                            <td>{{ $question->inspectionCategory?->name ?? '-' }}</td>
                            <td>{{ $question->input_type }}</td>
                            <td>{{ $question->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.knowledge-base.questions.update', ['question' => $question->id]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="code" value="{{ $question->code }}" required style="max-width: 90px;">
                                    <textarea name="prompt" rows="2" required style="max-width: 180px;">{{ $question->prompt }}</textarea>
                                    <select name="inspection_category_id" style="max-width: 110px;">
                                        @foreach ($inspectionCategories as $inspectionCategory)
                                            <option value="{{ $inspectionCategory->id }}" {{ $question->inspection_category_id == $inspectionCategory->id ? 'selected' : '' }}>{{ $inspectionCategory->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="input_type" style="max-width: 90px;">
                                        <option value="single_choice" {{ $question->input_type === 'single_choice' ? 'selected' : '' }}>single_choice</option>
                                        <option value="multiple_choice" {{ $question->input_type === 'multiple_choice' ? 'selected' : '' }}>multiple_choice</option>
                                        <option value="boolean" {{ $question->input_type === 'boolean' ? 'selected' : '' }}>boolean</option>
                                        <option value="text" {{ $question->input_type === 'text' ? 'selected' : '' }}>text</option>
                                        <option value="number" {{ $question->input_type === 'number' ? 'selected' : '' }}>number</option>
                                    </select>
                                    <select name="is_active" style="max-width: 90px;">
                                        <option value="1" {{ $question->is_active ? 'selected' : '' }}>Aktif</option>
                                        <option value="0" {{ ! $question->is_active ? 'selected' : '' }}>Nonaktif</option>
                                    </select>
                                    <button class="button button-quiet" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.knowledge-base.questions.destroy', ['question' => $question->id]) }}" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button" type="submit" onclick="return confirm('Hapus pertanyaan ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Belum ada pertanyaan yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="report-grid">
        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Pilihan</p>
                    <h2>Tambah opsi jawaban</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.knowledge-base.question-options.store') }}" class="inspection-form">
                @csrf
                <div class="form-grid">
                    <label>
                        Pertanyaan
                        <select name="question_id" required>
                            <option value="">-- Pilih pertanyaan --</option>
                            @foreach ($questions as $question)
                                <option value="{{ $question->id }}" {{ old('question_id') == $question->id ? 'selected' : '' }}>{{ $question->code }} — {{ $question->prompt }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Fakta terkait
                        <select name="fact_id">
                            <option value="">-- Tidak terkait fakta --</option>
                            @foreach ($facts as $fact)
                                <option value="{{ $fact->id }}" {{ old('fact_id') == $fact->id ? 'selected' : '' }}>{{ $fact->name }} ({{ $fact->code }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Kode opsi
                        <input type="text" name="code" value="{{ old('code') }}" required>
                    </label>
                    <label>
                        Label opsi
                        <input type="text" name="label" value="{{ old('label') }}" required>
                    </label>
                    <label>
                        Nilai opsi
                        <input type="text" name="value" value="{{ old('value') }}">
                    </label>
                    <label>
                        CF opsi
                        <input type="number" step="0.01" min="0" max="1" name="certainty_factor" value="{{ old('certainty_factor', 1) }}" required>
                    </label>
                    <label>
                        Urutan
                        <input type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', 0) }}">
                    </label>
                    <label class="checkbox-field">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Aktif
                    </label>
                </div>
                <button class="button" type="submit">Simpan opsi jawaban</button>
            </form>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Inventaris</p>
                    <h2>Opsi jawaban</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Pertanyaan</th>
                            <th>Kode</th>
                            <th>Label</th>
                            <th>CF</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($questionOptions as $questionOption)
                            <tr>
                                <td>{{ $questionOption->question?->code ?? '-' }}</td>
                                <td>{{ $questionOption->code }}</td>
                                <td>{{ $questionOption->label }}</td>
                                <td>{{ number_format($questionOption->certainty_factor, 2) }}</td>
                                <td>{{ $questionOption->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.knowledge-base.question-options.update', ['questionOption' => $questionOption->id]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                        @csrf
                                        @method('PUT')
                                        <select name="question_id" style="max-width: 140px;">
                                            @foreach ($questions as $question)
                                                <option value="{{ $question->id }}" {{ $questionOption->question_id == $question->id ? 'selected' : '' }}>{{ $question->code }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="code" value="{{ $questionOption->code }}" required style="max-width: 90px;">
                                        <input type="text" name="label" value="{{ $questionOption->label }}" required style="max-width: 120px;">
                                        <input type="number" step="0.01" min="0" max="1" name="certainty_factor" value="{{ $questionOption->certainty_factor }}" style="max-width: 70px;">
                                        <select name="is_active" style="max-width: 90px;">
                                            <option value="1" {{ $questionOption->is_active ? 'selected' : '' }}>Aktif</option>
                                            <option value="0" {{ ! $questionOption->is_active ? 'selected' : '' }}>Nonaktif</option>
                                        </select>
                                        <button class="button button-quiet" type="submit">Update</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.knowledge-base.question-options.destroy', ['questionOption' => $questionOption->id]) }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button" type="submit" onclick="return confirm('Hapus opsi ini?')">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Belum ada opsi jawaban.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
