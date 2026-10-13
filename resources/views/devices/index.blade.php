@extends('layouts.app')

@section('title', 'Katalog perangkat | Tilik')

@section('content')
<div class="page-wrap catalog-page">
    <div class="breadcrumbs"><a href="{{ route('dashboard') }}">Ruang kerja</a><span>/</span><span>Katalog perangkat</span></div>
    <section class="history-heading">
        <div>
            <p class="eyebrow">KATALOG PERANGKAT <span class="eyebrow-rule"></span> ANDROID & IPHONE</p>
            <h1>Cari perangkat</h1>
            <p class="intro-copy">Temukan model dari berbagai merek, lalu simpan spesifikasinya ke katalog perangkat.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert">{{ $errors->first() }}</div>
    @endif

    @if (session('status'))
        <div class="catalog-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($selectedDevice)
        @php
            $storageValues = is_array($selectedDevice->storage)
                ? collect($selectedDevice->storage)->flatten()
                : (is_string($selectedDevice->storage) ? collect([$selectedDevice->storage]) : collect());
            $storageVariants = $storageValues
                ->filter(fn ($value) => is_string($value) || is_numeric($value))
                ->flatMap(fn ($value) => explode(',', (string) $value))
                ->map(fn ($variant) => trim($variant))
                ->filter()
                ->unique()
                ->values();
        @endphp
        <section class="catalog-selected" aria-labelledby="selected-device-heading">
            <div>
                <p class="eyebrow">PERANGKAT TERSIMPAN</p>
                <h2 id="selected-device-heading">{{ $selectedDevice->name }}</h2>
                <p>{{ $selectedDevice->manufacturer_name ?: 'Merek tidak tersedia' }} · {{ $selectedDevice->device_type ?: 'Jenis tidak tersedia' }}</p>
                @php
                    $marketValue = $selectedDevice->marketValues->first(fn ($marketValue) => $marketValue->condition === 'baik');
                @endphp
                @if ($marketValue)
                    <p class="catalog-market-estimate">
                        Estimasi pasar kondisi baik ({{ $marketValue->storage_variant ?: 'semua storage' }}):
                        Rp {{ number_format((float) $marketValue->market_price_min, 0, ',', '.') }} –
                        Rp {{ number_format((float) $marketValue->market_price_max, 0, ',', '.') }}
                    </p>
                @endif
            </div>
            @if ($selectedDevice->image_url && in_array(parse_url($selectedDevice->image_url, PHP_URL_SCHEME), ['http', 'https'], true))
                <img src="{{ $selectedDevice->image_url }}" alt="Gambar {{ $selectedDevice->name }}" loading="lazy">
            @endif
            @if ($deviceTypes->isNotEmpty())
                <form class="catalog-assessment-form" method="POST" action="{{ route('assessments.store') }}">
                    @csrf
                    <input type="hidden" name="device_id" value="{{ $selectedDevice->id }}">
                    <label for="assessment-device-type">Pilih profil pertanyaan pemeriksaan</label>
                    <select id="assessment-device-type" name="device_type_id" required>
                        <option value="">Pilih profil</option>
                        @foreach ($deviceTypes as $deviceType)
                            <option value="{{ $deviceType->id }}" @selected(old('device_type_id') == $deviceType->id)>{{ $deviceType->name }}</option>
                        @endforeach
                    </select>
                    @if ($storageVariants->isNotEmpty())
                        <label for="assessment-storage-variant">Storage Variant</label>
                        <select id="assessment-storage-variant" name="storage_variant" required>
                            <option value="">Pilih storage</option>
                            @foreach ($storageVariants as $storageVariant)
                                <option value="{{ $storageVariant }}" @selected(old('storage_variant') === $storageVariant)>{{ $storageVariant }}</option>
                            @endforeach
                        </select>
                        @error('storage_variant') <span class="field-error">{{ $message }}</span> @enderror
                    @endif
                    <label for="catalog-purchase-price">Harga Beli <small>opsional</small></label>
                    <input id="catalog-purchase-price" type="number" name="purchase_price" value="{{ old('purchase_price') }}" min="0.01" max="9999999999999.99" step="0.01" inputmode="decimal">
                    @error('purchase_price') <span class="field-error">{{ $message }}</span> @enderror
                    <small>Profil menentukan pertanyaan sistem pakar; pilihan ini tidak mengubah data perangkat katalog.</small>
                    <button class="button button-dark" type="submit">Mulai penilaian perangkat ini <span aria-hidden="true">→</span></button>
                </form>
            @endif
        </section>
    @endif

    <section class="catalog-search-section" aria-labelledby="catalog-search-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow">PENCARIAN <span class="eyebrow-rule"></span> 01</p>
                <h2 id="catalog-search-heading">Nama atau model perangkat</h2>
            </div>
        </div>
        <form class="catalog-search-form" method="POST" action="{{ route('devices.search') }}">
            @csrf
            <label for="device-name">Contoh: Samsung Galaxy S24, iPhone 15, Xiaomi Redmi Note 13</label>
            <div class="catalog-search-controls">
                <input id="device-name" type="search" name="name" value="{{ old('name', $searchedName) }}" maxlength="100" required>
                <button class="button button-dark" type="submit">Cari perangkat <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>

    @if ($searchedName !== '')
        <section class="catalog-results-section" aria-labelledby="catalog-results-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">HASIL PENCARIAN <span class="eyebrow-rule"></span> {{ $searchResults ? count($searchResults) : '0' }}</p>
                    <h2 id="catalog-results-heading">Hasil untuk “{{ $searchedName }}”</h2>
                </div>
            </div>

            @if ($searchResults === [])
                <div class="empty-state">Tidak ada perangkat yang cocok. Coba nama atau model lain.</div>
            @else
                <div class="catalog-results-grid">
                    @foreach ($searchResults as $device)
                        @php
                            $deviceName = is_scalar($device['name']) ? (string) $device['name'] : '';
                            $manufacturerName = is_scalar($device['manufacturer_name']) ? (string) $device['manufacturer_name'] : '';
                            $deviceType = is_scalar($device['device_type']) ? (string) $device['device_type'] : '';
                            $isSaved = in_array($device['mobile_api_id'], $savedMobileApiIds, true);
                            $imageUrl = $device['image_url'];
                            $hardware = $device['hardware'];
                            $formatPreviewValue = function (mixed $value) use (&$formatPreviewValue): string {
                                if (is_array($value)) {
                                    $parts = [];
                                    foreach ($value as $key => $nestedValue) {
                                        if (is_string($key) && str_contains(strtolower($key), 'image_b64')) {
                                            continue;
                                        }

                                        $formattedValue = $formatPreviewValue($nestedValue);
                                        if ($formattedValue === '') {
                                            continue;
                                        }

                                        $parts[] = is_string($key)
                                            ? str_replace('_', ' ', ucfirst($key)).': '.$formattedValue
                                            : $formattedValue;
                                    }

                                    return implode(', ', $parts);
                                }

                                return is_scalar($value) && $value !== '' && $value !== false
                                    ? (string) $value
                                    : '';
                            };
                            $findHardwareValue = function (mixed $value, array $fieldNames) use (&$findHardwareValue): mixed {
                                if (! is_array($value)) {
                                    return null;
                                }

                                foreach ($value as $key => $nestedValue) {
                                    $normalizedKey = is_string($key)
                                        ? strtolower(preg_replace('/[^a-z0-9]/i', '', $key))
                                        : '';
                                    if (in_array($normalizedKey, $fieldNames, true) && $nestedValue !== null && $nestedValue !== '') {
                                        return $nestedValue;
                                    }
                                }

                                foreach ($value as $nestedValue) {
                                    $foundValue = $findHardwareValue($nestedValue, $fieldNames);
                                    if ($foundValue !== null && $foundValue !== '') {
                                        return $foundValue;
                                    }
                                }

                                return null;
                            };
                            $previewSpecifications = [
                                ['label' => 'Manufacturer', 'value' => $device['manufacturer_name']],
                                ['label' => 'Model', 'value' => $device['model_numbers']],
                                ['label' => 'Storage', 'value' => $device['storage']],
                                ['label' => 'RAM', 'value' => $findHardwareValue($hardware, ['ram', 'randomaccessmemory'])],
                                ['label' => 'Display', 'value' => $device['screen_resolution']],
                                ['label' => 'Processor', 'value' => $findHardwareValue($hardware, ['processor', 'cpu', 'chipset', 'soc'])],
                                ['label' => 'Battery', 'value' => $device['battery_capacity']],
                                ['label' => 'Camera', 'value' => $device['camera']],
                                ['label' => 'OS', 'value' => $findHardwareValue($hardware, ['os', 'operatingsystem'])],
                                ['label' => 'Network', 'value' => $findHardwareValue($hardware, ['network'])],
                                ['label' => 'Release date', 'value' => $device['release_date']],
                            ];
                        @endphp
                        <article class="catalog-device-card">
                            @if (is_string($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL) && in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true))
                                <img class="catalog-device-image" src="{{ $imageUrl }}" alt="Gambar {{ $deviceName }}" loading="lazy">
                            @else
                                <div class="catalog-device-image-placeholder">Gambar tidak tersedia</div>
                            @endif
                            <div class="catalog-device-copy">
                                <p class="eyebrow">{{ $manufacturerName !== '' ? $manufacturerName : 'Merek tidak tersedia' }} <span class="eyebrow-rule"></span> {{ $deviceType !== '' ? $deviceType : 'Jenis tidak tersedia' }}</p>
                                <h3>{{ $deviceName !== '' ? $deviceName : 'Nama perangkat tidak tersedia' }}</h3>
                                <p class="catalog-device-model">{{ $formatPreviewValue($device['model_numbers']) ?: 'Model tidak tersedia' }}</p>
                                @if ($device['mobile_api_id'] !== null && filled($deviceName))
                                    @if ($isSaved)
                                        <button class="button button-quiet" type="button" disabled>Sudah tersimpan</button>
                                    @else
                                        <button class="button button-dark" type="button" data-open-device-preview="device-preview-{{ $loop->index }}" aria-haspopup="dialog" aria-controls="device-preview-{{ $loop->index }}">Lihat Preview <span aria-hidden="true">→</span></button>
                                    @endif
                                @endif
                            </div>
                        </article>
                        @if ($device['mobile_api_id'] !== null && filled($deviceName) && ! $isSaved)
                            <dialog class="catalog-preview-dialog" id="device-preview-{{ $loop->index }}" aria-labelledby="device-preview-title-{{ $loop->index }}">
                                <div class="catalog-preview-content">
                                    <div class="catalog-preview-heading">
                                        <div>
                                            <p class="eyebrow">PREVIEW PERANGKAT</p>
                                            <h2 id="device-preview-title-{{ $loop->index }}">{{ $deviceName }}</h2>
                                        </div>
                                        <button class="catalog-preview-close" type="button" data-close-device-preview aria-label="Tutup preview">×</button>
                                    </div>
                                    <div class="catalog-preview-overview">
                                        @if (is_string($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL) && in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true))
                                            <img src="{{ $imageUrl }}" alt="Gambar {{ $deviceName }}" loading="lazy">
                                        @else
                                            <div class="catalog-preview-image-placeholder">Tidak tersedia</div>
                                        @endif
                                        <div>
                                            <p class="eyebrow">{{ $manufacturerName !== '' ? $manufacturerName : 'Tidak tersedia' }} <span class="eyebrow-rule"></span> {{ $deviceType !== '' ? $deviceType : 'Tidak tersedia' }}</p>
                                            <h3>{{ $deviceName }}</h3>
                                        </div>
                                    </div>
                                    <dl class="catalog-preview-specifications">
                                        @foreach ($previewSpecifications as $specification)
                                            @php
                                                $formattedValue = $formatPreviewValue($specification['value']);
                                            @endphp
                                            <div>
                                                <dt>{{ $specification['label'] }}</dt>
                                                <dd>{{ $formattedValue !== '' ? $formattedValue : 'Tidak tersedia' }}</dd>
                                            </div>
                                        @endforeach
                                        @php
                                            $formattedHardware = $formatPreviewValue($hardware);
                                        @endphp
                                        @if ($formattedHardware !== '')
                                            <div>
                                                <dt>Hardware</dt>
                                                <dd>{{ $formattedHardware }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                    <div class="catalog-preview-actions">
                                        <button class="button button-quiet" type="button" data-close-device-preview>Kembali</button>
                                        <form method="POST" action="{{ route('devices.select') }}">
                                            @csrf
                                            <input type="hidden" name="mobile_api_id" value="{{ $device['mobile_api_id'] }}">
                                            <input type="hidden" name="name" value="{{ $deviceName }}">
                                            <button class="button button-dark" type="submit">Simpan ke Katalog <span aria-hidden="true">→</span></button>
                                        </form>
                                    </div>
                                </div>
                            </dialog>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section class="catalog-saved-section" aria-labelledby="saved-devices-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Data Perangkat Anda <span class="eyebrow-rule"></span> {{ $savedDevices->total() }}</p>
                <h2 id="saved-devices-heading">Perangkat tersimpan</h2>
            </div>
        </div>
        @if ($savedDevices->isEmpty())
            <div class="empty-state">Perangkat yang dipilih dari hasil pencarian akan tersimpan di sini.</div>
        @else
            <div class="catalog-saved-list">
                @foreach ($savedDevices as $device)
                    @php
                        $marketValue = $device->marketValues->first(fn ($marketValue) => $marketValue->condition === 'baik');
                    @endphp
                    <article class="catalog-saved-row">
                        <div>
                            <h3>{{ $device->name }}</h3>
                            <p>{{ $device->manufacturer_name ?: 'Merek tidak tersedia' }} · {{ $device->device_type ?: 'Jenis tidak tersedia' }}</p>
                            @if ($marketValue)
                                <p class="catalog-market-estimate">
                                    Estimasi pasar kondisi baik ({{ $marketValue->storage_variant ?: 'semua storage' }}):
                                    Rp {{ number_format((float) $marketValue->market_price_min, 0, ',', '.') }} –
                                    Rp {{ number_format((float) $marketValue->market_price_max, 0, ',', '.') }}
                                </p>
                                @if (filled($marketValue->source))
                                    <small class="catalog-market-source">{{ $marketValue->source }}</small>
                                @endif
                            @else
                                <p class="catalog-market-estimate">Estimasi harga pasar belum tersedia.</p>
                            @endif
                        </div>
                        <span class="result-pill result-pending">Tersimpan</span>
                    </article>
                @endforeach
            </div>
            <div class="pagination-wrap">{{ $savedDevices->links() }}</div>
        @endif
    </section>
</div>
@endsection
