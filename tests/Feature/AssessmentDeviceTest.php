<?php

namespace Tests\Feature;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSession;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Question;
use App\Models\RepairCost;
use App\Models\User;
use App\Services\ExpertSystem\ExpertEvaluationService;
use App\Services\RepairCostService;
use Database\Seeders\ExpertSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentDeviceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function android_catalog_device_is_saved_on_the_session_and_shown_during_inspection(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'android-100',
            'name' => 'Samsung Galaxy S24',
            'manufacturer_name' => 'Samsung',
            'device_type' => 'Smartphone',
            'image_url' => 'https://example.com/galaxy-s24.jpg',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertDatabaseHas('assessment_sessions', [
            'id' => $session->id,
            'device_id' => $device->id,
            'device_type_id' => $deviceType->id,
        ]);
        $this->assertSame($device->id, $session->device->id);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('Samsung Galaxy S24')
            ->assertSee('Samsung')
            ->assertSee('https://example.com/galaxy-s24.jpg');
    }

    #[Test]
    public function iphone_catalog_device_is_saved_on_the_session_and_shown_during_inspection(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'iphone-100',
            'name' => 'iPhone 15',
            'manufacturer_name' => 'Apple',
            'device_type' => 'Smartphone',
            'image_url' => 'https://example.com/iphone-15.jpg',
            'storage' => '128GB, 256GB, 1TB',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'storage_variant' => '128GB',
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertDatabaseHas('assessment_sessions', [
            'id' => $session->id,
            'device_id' => $device->id,
            'device_type_id' => $deviceType->id,
            'storage_variant' => '128GB',
        ]);
        $this->assertSame('128GB', $session->storage_variant);
        $this->assertSame($device->id, $session->device->id);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('iPhone 15')
            ->assertSee('Apple')
            ->assertSee('Storage:')
            ->assertSee('128GB')
            ->assertSee('https://example.com/iphone-15.jpg');
    }

    #[Test]
    public function catalog_assessment_form_lists_individual_storage_variants(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'storage-options-1',
            'name' => 'iPhone 13 Pro Max',
            'storage' => '128GB, 256GB, 1TB',
        ]);

        $this->withSession(['selected_device_id' => $device->id])
            ->get(route('devices.index'))
            ->assertSee('name="storage_variant"', false)
            ->assertSee('value="128GB"', false)
            ->assertSee('value="256GB"', false)
            ->assertSee('value="1TB"', false);
    }

    #[Test]
    #[DataProvider('validStorageVariants')]
    public function catalog_assessment_accepts_each_storage_variant_listed_for_the_device(string $storageVariant): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'storage-valid-'.str_replace('/', '-', $storageVariant),
            'name' => 'iPhone 13 Pro Max',
            'storage' => '128GB, 256GB, 1TB',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'storage_variant' => $storageVariant,
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertSame($storageVariant, $session->storage_variant);
    }

    #[Test]
    #[DataProvider('invalidStorageVariants')]
    public function catalog_assessment_rejects_storage_variants_not_listed_for_the_device(string $storageVariant): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'storage-invalid-'.str_replace(' ', '-', $storageVariant),
            'name' => 'iPhone 13 Pro Max',
            'storage' => '128GB, 256GB, 1TB',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();

        $this->from(route('devices.index'))
            ->post(route('assessments.store'), [
                'device_type_id' => $deviceType->id,
                'device_id' => $device->id,
                'storage_variant' => $storageVariant,
            ])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHasErrors('storage_variant');

        $this->assertDatabaseCount('assessment_sessions', 0);
    }

    #[Test]
    public function catalog_selection_form_posts_the_saved_device_id_and_available_assessment_profiles(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'pixel-100',
            'name' => 'Google Pixel',
            'manufacturer_name' => 'Google',
        ]);

        $this->withSession(['selected_device_id' => $device->id])
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Google Pixel')
            ->assertSee('name="device_id" value="'.$device->id.'"', false)
            ->assertSee('Pilih profil pertanyaan pemeriksaan')
            ->assertSee('Smartphone Android')
            ->assertSee('iPhone');
    }

    #[Test]
    public function invalid_device_id_is_rejected_without_creating_an_assessment_session(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $this->from(route('devices.index'))
            ->post(route('assessments.store'), [
                'workspace' => '1',
                'device_type_id' => $deviceType->id,
                'device_id' => 999999,
            ])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHasErrors('device_id');

        $this->assertDatabaseCount('assessment_sessions', 0);
    }

    #[Test]
    public function existing_assessment_without_a_device_still_starts_and_displays_its_legacy_label(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_label' => 'Legacy device label',
        ])
            ->assertRedirect();

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $this->assertNull($session->device_id);
        $this->assertNull($session->storage_variant);
        $this->assertNull($session->device);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('Legacy device label')
            ->assertDontSee('Merek tidak tersedia');
    }

    #[Test]
    public function selected_device_and_assessment_profile_are_persisted_without_accepting_browser_specifications(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'generic-100',
            'name' => 'Generic catalog phone',
            'manufacturer_name' => 'Catalog maker',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'manufacturer_name' => 'Forged maker',
            'image_url' => 'https://attacker.example/fake.jpg',
            'hardware' => 'Forged hardware',
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertSame($device->id, $session->device_id);
        $this->assertSame('Catalog maker', $session->device->manufacturer_name);
        $this->assertSame(['label' => 'Generic catalog phone'], $session->device_details);
    }

    #[Test]
    public function storage_variant_does_not_change_expert_diagnosis_certainty_or_repair_estimates(): void
    {
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice([
            'mobile_api_id' => 'storage-invariance',
            'name' => 'iPhone 13 Pro Max',
            'storage' => '128GB, 256GB, 1TB',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();
        $sessions = collect([
            AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'device_id' => $device->id,
                'status' => 'in_progress',
            ]),
            AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'device_id' => $device->id,
                'storage_variant' => '128GB',
                'status' => 'in_progress',
            ]),
        ]);

        foreach ($sessions as $session) {
            foreach (Question::query()->with('options')->get() as $question) {
                $option = $question->options->firstWhere('code', 'normal');
                $session->answers()->create([
                    'question_id' => $question->id,
                    'question_option_id' => $option->id,
                    'answer_state' => AssessmentAnswer::STATE_KNOWN,
                    'evidence_source' => 'DIRECT_INSPECTION',
                ]);
            }
        }

        $evaluationService = app(ExpertEvaluationService::class);
        $withoutStorageResult = $evaluationService->evaluate($sessions[0]);
        $withStorageResult = $evaluationService->evaluate($sessions[1]);
        $repairCostService = app(RepairCostService::class);
        $withoutStorageEstimates = $repairCostService->estimatesFor($withoutStorageResult);
        $withStorageEstimates = $repairCostService->estimatesFor($withStorageResult);

        $this->assertSame($withoutStorageResult->classification, $withStorageResult->classification);
        $this->assertSame($withoutStorageResult->confidence_score, $withStorageResult->confidence_score);
        $this->assertSame($withoutStorageResult->contributing_factors, $withStorageResult->contributing_factors);
        $this->assertSame(
            $withoutStorageEstimates['entries']->map(fn (RepairCost $cost): array => $cost->only([
                'diagnosis_code',
                'minimum_cost',
                'maximum_cost',
            ]))->all(),
            $withStorageEstimates['entries']->map(fn (RepairCost $cost): array => $cost->only([
                'diagnosis_code',
                'minimum_cost',
                'maximum_cost',
            ]))->all()
        );
    }

    public static function validStorageVariants(): array
    {
        return [
            '128GB' => ['128GB'],
            '256GB' => ['256GB'],
            '1TB' => ['1TB'],
        ];
    }

    public static function invalidStorageVariants(): array
    {
        return [
            'unavailable capacity' => ['512GB'],
            'non-canonical spaced capacity' => ['128 GB'],
            'incomplete capacity' => ['256'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(array $attributes): Device
    {
        return Device::query()->create($attributes + [
            'mobile_api_id' => 'test-device',
            'name' => 'Test device',
        ]);
    }
}
