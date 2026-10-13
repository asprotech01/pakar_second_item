<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AssessmentSession;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\User;
use Database\Seeders\ExpertSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workspace_autocomplete_searches_local_devices_without_calling_mobile_api(): void
    {
        $this->actingAs(User::factory()->create());
        $device = Device::query()->create([
            'mobile_api_id' => 'vivo-3051',
            'name' => 'Vivo V30',
            'manufacturer_name' => 'Vivo',
            'storage' => '8/256GB, 12/512GB',
        ]);
        Device::query()->create([
            'mobile_api_id' => 'oppo-448',
            'name' => 'OPPO Reno 11',
            'manufacturer_name' => 'OPPO',
        ]);
        Http::preventStrayRequests();

        $this->getJson(route('devices.autocomplete', ['q' => 'Vivo']))
            ->assertOk()
            ->assertExactJson([
                'devices' => [[
                    'id' => $device->id,
                    'name' => 'Vivo V30',
                    'manufacturer_name' => 'Vivo',
                    'storage_variants' => ['8/256GB', '12/512GB'],
                ]],
            ]);

        Http::assertNothingSent();
    }

    #[Test]
    public function workspace_autocomplete_matches_device_manufacturer_and_limits_results(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 12) as $index) {
            Device::query()->create([
                'mobile_api_id' => 'vivo-'.$index,
                'name' => 'Phone '.$index,
                'manufacturer_name' => 'Vivo',
            ]);
        }

        $response = $this->getJson(route('devices.autocomplete', ['q' => 'Vivo']))
            ->assertOk()
            ->assertJsonCount(10, 'devices');

        $this->assertSame('Vivo', $response->json('devices.0.manufacturer_name'));
    }

    #[Test]
    public function workspace_autocomplete_returns_no_results_for_queries_shorter_than_two_characters(): void
    {
        $this->actingAs(User::factory()->create());
        Device::query()->create([
            'mobile_api_id' => 'vivo-3051',
            'name' => 'Vivo V30',
            'manufacturer_name' => 'Vivo',
        ]);

        $this->getJson(route('devices.autocomplete', ['q' => 'V']))
            ->assertOk()
            ->assertExactJson(['devices' => []]);
    }

    #[Test]
    public function workspace_renders_a_single_form_with_device_search_and_profile_fallback(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seed(ExpertSystemSeeder::class);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('devices.autocomplete'), false)
            ->assertSee('name="device_id"', false)
            ->assertSee('name="storage_variant"', false)
            ->assertSee('name="device_type_id"', false)
            ->assertSee('name="purchase_price"', false)
            ->assertSee('Smartphone Android')
            ->assertSee('iPhone')
            ->assertDontSee('name="device_label"', false);
    }

    #[Test]
    #[DataProvider('validStorageVariants')]
    public function workspace_assessment_persists_the_selected_device_storage_and_purchase_price(
        string $storageVariant
    ): void {
        $this->actingAs(User::factory()->create());
        $this->seed(ExpertSystemSeeder::class);
        $device = Device::query()->create([
            'mobile_api_id' => 'vivo-3051',
            'name' => 'Vivo V30',
            'manufacturer_name' => 'Vivo',
            'storage' => '8/256GB, 12/512GB',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'workspace' => '1',
            'device_id' => $device->id,
            'device_type_id' => $deviceType->id,
            'storage_variant' => $storageVariant,
            'purchase_price' => '4000000',
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertDatabaseHas('assessment_sessions', [
            'id' => $session->id,
            'device_id' => $device->id,
            'storage_variant' => $storageVariant,
            'purchase_price' => 4000000,
        ]);
        $this->assertSame(['label' => 'Vivo V30'], $session->device_details);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('Vivo V30')
            ->assertSee($storageVariant)
            ->assertSee('Rp 4.000.000,00');
    }

    #[Test]
    public function workspace_assessment_requires_a_selected_local_device(): void
    {
        $this->actingAs(User::factory()->create());
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $this->from(route('dashboard'))
            ->post(route('assessments.store'), [
                'workspace' => '1',
                'device_type_id' => $deviceType->id,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('device_id');

        $this->assertDatabaseCount('assessment_sessions', 0);
    }

    public static function validStorageVariants(): array
    {
        return [
            '8/256GB' => ['8/256GB'],
            '12/512GB' => ['12/512GB'],
        ];
    }
}
