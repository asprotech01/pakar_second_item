<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Device;
use App\Models\User;
use Database\Seeders\DeviceSeeder;
use Database\Seeders\MarketValueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function android_search_renders_matching_mobile_api_devices(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [$this->apiDevice([
                    'id' => 201,
                    'name' => 'Samsung Galaxy S24',
                    'manufacturer_name' => 'Samsung',
                    'device_type' => 'Smartphone',
                    'model_numbers' => ['SM-S921B'],
                    'colors' => null,
                    'hardware' => null,
                ])],
            ]),
        ]);

        $this->post(route('devices.search'), ['name' => 'Samsung Galaxy S24'])
            ->assertOk()
            ->assertSee('Samsung Galaxy S24')
            ->assertSee('Samsung')
            ->assertSee('SM-S921B')
            ->assertSee('Lihat Preview')
            ->assertSee('<dt>Processor</dt>', false)
            ->assertSee('<dd>Tidak tersedia</dd>', false)
            ->assertDontSee('Warna');

        $this->assertDatabaseCount('devices', 0);
        Http::assertSentCount(1);
    }

    #[Test]
    public function iphone_search_renders_matching_mobile_api_devices(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [$this->apiDevice([
                    'id' => 202,
                    'name' => 'iPhone 15',
                    'manufacturer_name' => 'Apple',
                    'device_type' => 'Smartphone',
                    'model_numbers' => ['A3090'],
                    'storage' => ['128GB'],
                    'screen_resolution' => '2556 x 1179',
                    'battery_capacity' => '3349 mAh',
                    'camera' => ['48 MP'],
                    'release_date' => '2023-09-22',
                    'hardware' => [
                        'ram' => '8GB',
                        'processor' => 'A16 Bionic',
                        'operating_system' => 'iOS 17',
                        'network' => '5G',
                    ],
                    'image_url' => 'https://example.com/iphone-15.jpg',
                ])],
            ]),
        ]);

        $this->post(route('devices.search'), ['name' => 'iPhone 15'])
            ->assertOk()
            ->assertSee('iPhone 15')
            ->assertSee('Apple')
            ->assertSee('id="device-preview-0"', false)
            ->assertSee('https://example.com/iphone-15.jpg')
            ->assertSee('A3090')
            ->assertSee('128GB')
            ->assertSee('8GB')
            ->assertSee('2556 x 1179')
            ->assertSee('A16 Bionic')
            ->assertSee('3349 mAh')
            ->assertSee('48 MP')
            ->assertSee('iOS 17')
            ->assertSee('5G')
            ->assertSee('2023-09-22')
            ->assertSee('Lihat Preview')
            ->assertSee('Kembali')
            ->assertSee('Simpan ke Katalog')
            ->assertSee('action="'.route('devices.select').'"', false)
            ->assertDontSee('image_b64');

        $this->assertDatabaseCount('devices', 0);
        Http::assertSentCount(1);
    }

    #[Test]
    public function empty_search_keyword_redirects_with_a_validation_error_without_calling_mobile_api(): void
    {
        $this->actingAs(User::factory()->create());
        Http::preventStrayRequests();

        $this->from(route('devices.index'))
            ->post(route('devices.search'), ['name' => ''])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function search_with_no_matches_displays_an_empty_result_message(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response(['devices' => []]),
        ]);

        $this->post(route('devices.search'), ['name' => 'Unknown model'])
            ->assertOk()
            ->assertSee('Tidak ada perangkat yang cocok.');
    }

    #[Test]
    public function mobile_api_error_returns_a_safe_catalog_error(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response(['message' => 'private upstream detail'], 503),
        ]);

        $this->followingRedirects()
            ->post(route('devices.search'), ['name' => 'iPhone 15'])
            ->assertOk()
            ->assertSee('Katalog perangkat sedang tidak dapat diakses.')
            ->assertDontSee('private upstream detail')
            ->assertDontSee('test-token');
    }

    #[Test]
    public function selecting_a_device_saves_canonical_api_data_and_ignores_browser_specifications(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [$this->apiDevice([
                    'id' => 203,
                    'name' => 'Xiaomi Redmi Note 13',
                    'manufacturer_name' => 'Xiaomi',
                    'device_type' => 'Smartphone',
                    'model_numbers' => ['2312DRAABG'],
                    'image_url' => 'https://example.com/redmi-note-13.jpg',
                    'image_b64' => 'upstream-base64-must-not-be-saved',
                ])],
            ]),
        ]);

        $this->post(route('devices.select'), [
            'mobile_api_id' => '203',
            'name' => 'Xiaomi Redmi Note 13',
            'manufacturer_name' => 'Forged manufacturer',
            'hardware' => 'Forged hardware',
            'image_url' => 'https://attacker.example/image.jpg',
            'image_b64' => 'forged-base64',
        ])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHas('status', 'Perangkat berhasil disimpan ke katalog.');

        $device = Device::query()->where('mobile_api_id', '203')->firstOrFail();

        $this->assertSame('Xiaomi', $device->manufacturer_name);
        $this->assertSame('2312DRAABG', $device->model_numbers[0]);
        $this->assertSame('https://example.com/redmi-note-13.jpg', $device->image_url);
        $this->assertArrayNotHasKey('image_b64', $device->getAttributes());
        $this->assertDatabaseCount('devices', 1);

        $this->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Xiaomi Redmi Note 13')
            ->assertSee('Perangkat berhasil disimpan ke katalog.');
    }

    #[Test]
    public function selecting_an_already_saved_mobile_api_id_does_not_create_a_duplicate_or_call_mobile_api(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();

        Device::query()->create([
            'mobile_api_id' => '204',
            'name' => 'OPPO Reno',
            'manufacturer_name' => 'OPPO',
        ]);

        $this->post(route('devices.select'), [
            'mobile_api_id' => '204',
            'name' => 'OPPO Reno',
        ])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHas('status', 'Perangkat ini sudah tersimpan di katalog.');

        $this->assertDatabaseCount('devices', 1);
    }

    #[Test]
    public function selecting_an_id_missing_from_mobile_api_results_does_not_save_a_device(): void
    {
        $this->actingAs(User::factory()->create());
        $this->configureMobileApi();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [$this->apiDevice([
                    'id' => 205,
                    'name' => 'Google Pixel 9',
                ])],
            ]),
        ]);

        $this->post(route('devices.select'), [
            'mobile_api_id' => '999',
            'name' => 'Google Pixel 9',
        ])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHasErrors([
                'catalog' => 'Perangkat tidak lagi tersedia dari katalog. Silakan cari ulang.',
            ]);

        $this->assertDatabaseCount('devices', 0);
    }

    #[Test]
    public function seeded_phone_catalog_displays_market_price_estimates_without_mobile_api_requests(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seed([DeviceSeeder::class, MarketValueSeeder::class]);
        Http::preventStrayRequests();

        $this->get(route('devices.index'))
            ->assertOk()
            ->assertSee('iPhone 13')
            ->assertSee('Galaxy S24')
            ->assertSee('Estimasi pasar kondisi baik (128GB)')
            ->assertSee('Rp 6.000.000')
            ->assertSee('Rp 8.100.000')
            ->assertSee('Estimasi pasar; bukan harga transaksi pasti.');

        $this->assertDatabaseHas('market_values', [
            'storage_variant' => '128GB',
            'condition' => 'baik',
            'market_price_min' => 6000000,
            'market_price_max' => 8100000,
        ]);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function apiDevice(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Generic Device',
            'manufacturer_name' => 'Generic manufacturer',
            'device_type' => 'Smartphone',
            'model_numbers' => [],
            'colors' => [],
            'storage' => ['128GB'],
            'screen_resolution' => null,
            'weight' => null,
            'thickness' => null,
            'release_date' => null,
            'camera' => null,
            'battery_capacity' => null,
            'hardware' => null,
            'image_url' => null,
        ], $overrides);
    }

    private function configureMobileApi(): void
    {
        config()->set('services.mobile_api.base_url', 'https://api.mobileapi.dev');
        config()->set('services.mobile_api.key', 'test-token');
    }
}
