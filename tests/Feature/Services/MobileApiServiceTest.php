<?php

namespace Tests\Feature\Services;

use App\Services\MobileApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileApiServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_returns_catalog_fields_and_excludes_the_base64_image(): void
    {
        config()->set('services.mobile_api.base_url', 'https://api.mobileapi.dev');
        config()->set('services.mobile_api.key', 'test-token');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [[
                    'id' => 123,
                    'match_certainty' => 0.99,
                    'name' => 'iPhone 15',
                    'manufacturer_name' => 'Apple',
                    'device_type' => 'Smartphone',
                    'model_numbers' => ['A3090'],
                    'colors' => ['Black'],
                    'storage' => ['128GB'],
                    'screen_resolution' => '2556 x 1179',
                    'weight' => '171 g',
                    'thickness' => '7.8 mm',
                    'release_date' => '2023-09-22',
                    'camera' => ['48 MP'],
                    'battery_capacity' => '3349 mAh',
                    'hardware' => ['A16 Bionic'],
                    'image_url' => 'https://example.com/iphone-15.jpg',
                    'image_b64' => 'must-not-be-returned-or-saved',
                ]],
            ]),
        ]);

        $devices = app(MobileApiService::class)->searchDevices(' iPhone 15 ');

        $this->assertSame([[
            'mobile_api_id' => '123',
            'match_certainty' => 0.99,
            'name' => 'iPhone 15',
            'manufacturer_name' => 'Apple',
            'device_type' => 'Smartphone',
            'model_numbers' => ['A3090'],
            'colors' => ['Black'],
            'storage' => ['128GB'],
            'screen_resolution' => '2556 x 1179',
            'weight' => '171 g',
            'thickness' => '7.8 mm',
            'release_date' => '2023-09-22',
            'camera' => ['48 MP'],
            'battery_capacity' => '3349 mAh',
            'hardware' => ['A16 Bionic'],
            'image_url' => 'https://example.com/iphone-15.jpg',
        ]], $devices);

        Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/devices/search/'
            && $request->data() === ['name' => 'iPhone 15']
            && $request->hasHeader('Authorization'));
    }

    #[Test]
    public function search_uses_memory_internal_when_storage_is_empty_and_extracts_ordered_unique_capacities(): void
    {
        config()->set('services.mobile_api.base_url', 'https://api.mobileapi.dev');
        config()->set('services.mobile_api.key', 'test-token');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [[
                    'id' => 493,
                    'name' => 'Redmi Note 13',
                    'manufacturer_name' => 'Xiaomi',
                    'storage' => '',
                ]],
            ]),
            'https://api.mobileapi.dev/devices/493/memory/' => Http::response([
                'id' => 11602,
                'internal' => '128GB 6GB RAM, 128GB 8GB RAM, 256GB 8GB RAM, 256GB 12GB RAM, 512GB 12GB RAM',
            ]),
        ]);

        $devices = app(MobileApiService::class)->searchDevices('Redmi Note 13');
        $device = app(MobileApiService::class)->saveDevice($devices[0]);

        $this->assertSame(['128GB', '256GB', '512GB'], $devices[0]['storage']);
        $this->assertSame(['128GB', '256GB', '512GB'], $device->storageVariants());
        $this->assertDatabaseHas('devices', [
            'mobile_api_id' => '493',
            'name' => 'Redmi Note 13',
        ]);
        Http::assertSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/devices/493/memory/'
            && $request->hasHeader('Authorization'));
    }

    #[Test]
    public function search_uses_available_storage_without_requesting_memory_specs(): void
    {
        config()->set('services.mobile_api.base_url', 'https://api.mobileapi.dev');
        config()->set('services.mobile_api.key', 'test-token');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response([
                'devices' => [[
                    'id' => 576,
                    'name' => 'Galaxy S24',
                    'manufacturer_name' => 'Samsung',
                    'storage' => '128GB, 256GB, 512GB',
                ]],
            ]),
        ]);

        $devices = app(MobileApiService::class)->searchDevices('Galaxy S24');

        $this->assertSame('128GB, 256GB, 512GB', $devices[0]['storage']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function search_throws_when_mobile_api_returns_an_http_error(): void
    {
        config()->set('services.mobile_api.base_url', 'https://api.mobileapi.dev');
        config()->set('services.mobile_api.key', 'test-token');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobileapi.dev/devices/search/*' => Http::response(['message' => 'Unavailable'], 503),
        ]);

        $this->expectException(RequestException::class);

        app(MobileApiService::class)->searchDevices('iPhone 15');
    }

    #[Test]
    public function saving_a_search_result_persists_device_data_without_base64_and_updates_by_mobile_api_id(): void
    {
        $service = app(MobileApiService::class);
        $deviceData = [
            'mobile_api_id' => '123',
            'match_certainty' => 0.99,
            'name' => 'iPhone 15',
            'manufacturer_name' => 'Apple',
            'device_type' => 'Smartphone',
            'model_numbers' => ['A3090'],
            'colors' => ['Black'],
            'storage' => ['128GB'],
            'screen_resolution' => '2556 x 1179',
            'weight' => '171 g',
            'thickness' => '7.8 mm',
            'release_date' => '2023-09-22',
            'camera' => ['48 MP'],
            'battery_capacity' => '3349 mAh',
            'hardware' => ['A16 Bionic'],
            'image_url' => 'https://example.com/iphone-15.jpg',
            'image_b64' => 'must-not-be-saved',
        ];

        $device = $service->saveDevice($deviceData);
        $updatedDevice = $service->saveDevice(array_merge($deviceData, ['name' => 'iPhone 15 (updated)']));

        $this->assertModelExists($device);
        $this->assertSame($device->id, $updatedDevice->id);
        $this->assertSame('iPhone 15 (updated)', $updatedDevice->name);
        $this->assertSame(['A3090'], $updatedDevice->model_numbers);
        $this->assertDatabaseHas('devices', [
            'mobile_api_id' => '123',
            'name' => 'iPhone 15 (updated)',
            'image_url' => 'https://example.com/iphone-15.jpg',
        ]);
        $this->assertDatabaseCount('devices', 1);
        $this->assertArrayNotHasKey('image_b64', $updatedDevice->getAttributes());
        $this->assertArrayNotHasKey('match_certainty', $updatedDevice->getAttributes());
    }

    #[Test]
    public function search_rejects_an_empty_device_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(MobileApiService::class)->searchDevices('  ');
    }
}
