<?php

namespace Tests\Feature\Services;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentResult;
use App\Models\AssessmentSession;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\MarketValue;
use App\Models\Question;
use App\Models\User;
use App\Services\ExpertSystem\ExpertEvaluationService;
use App\Services\MarketValueService;
use App\Services\PurchaseEvaluationService;
use App\Services\RepairCostService;
use Database\Seeders\DeviceSeeder;
use Database\Seeders\ExpertSystemSeeder;
use Database\Seeders\MarketValueSeeder;
use Database\Seeders\RepairCostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketValueServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function market_value_is_returned_for_its_device_with_decimal_amounts(): void
    {
        $device = $this->createDevice('value-device-1', 'Samsung Galaxy S24');
        $marketValue = $this->createMarketValue($device, [
            'storage_variant' => '256 GB',
            'condition' => 'Bekas baik',
            'market_price_min' => '7500000.00',
            'market_price_max' => '9500000.00',
        ]);

        $selectedMarketValue = app(MarketValueService::class)->forDevice(
            $device,
            '256 GB',
            'Bekas baik'
        );

        $this->assertSame($marketValue->id, $selectedMarketValue?->id);
        $this->assertSame('7500000.00', $selectedMarketValue?->market_price_min);
        $this->assertSame('9500000.00', $selectedMarketValue?->market_price_max);
        $this->assertSame($device->id, $selectedMarketValue?->device_id);
        $this->assertSame($device->id, $selectedMarketValue?->device->id);
        $this->assertTrue($device->marketValues->contains('id', $marketValue->id));
    }

    #[Test]
    public function market_value_selection_prefers_matching_storage_and_condition_over_generic_data(): void
    {
        $device = $this->createDevice('value-device-2', 'Samsung Galaxy S24');
        $genericValue = $this->createMarketValue($device, [
            'market_price_min' => '7000000.00',
            'market_price_max' => '9000000.00',
            'observed_at' => '2026-10-01 00:00:00',
        ]);
        $specificValue = $this->createMarketValue($device, [
            'storage_variant' => '256 GB',
            'condition' => 'Bekas baik',
            'market_price_min' => '7500000.00',
            'market_price_max' => '9500000.00',
            'observed_at' => '2026-09-30 00:00:00',
        ]);

        $selectedMarketValue = app(MarketValueService::class)->forDevice(
            $device,
            '256 GB',
            'Bekas baik'
        );

        $this->assertSame($specificValue->id, $selectedMarketValue?->id);
        $this->assertNotSame($genericValue->id, $selectedMarketValue?->id);
    }

    #[Test]
    public function device_without_market_value_returns_no_value(): void
    {
        $device = $this->createDevice('value-device-3', 'Unpriced device');

        $marketValue = app(MarketValueService::class)->forDevice($device);

        $this->assertNull($marketValue);
    }

    #[Test]
    public function market_value_seeder_only_seeds_devices_that_already_exist(): void
    {
        $this->seed(MarketValueSeeder::class);

        $this->assertDatabaseCount('market_values', 0);
        $this->assertDatabaseCount('devices', 0);
    }

    #[Test]
    public function seeded_catalog_phone_values_cover_recent_512gb_assessments_and_enable_price_comparison(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);
        $this->seed([
            DeviceSeeder::class,
            ExpertSystemSeeder::class,
            MarketValueSeeder::class,
            RepairCostSeeder::class,
        ]);

        $device = Device::query()->where('mobile_api_id', '1748')->firstOrFail();
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'user_id' => $admin->id,
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'storage_variant' => '512GB',
            'purchase_price' => '10000000.00',
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $session->id,
            'classification' => 'bersyarat',
            'confidence_score' => 98.0,
            'contributing_factors' => [
                ['fact_code' => 'camera_condition_issue'],
                ['fact_code' => 'charging_condition_issue'],
            ],
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Confidence diagnosis memakai CF dari rule terkuat')
            ->assertSee('512GB')
            ->assertSee('Rp 20.000.000,00 – Rp 24.000.000,00')
            ->assertSee('Estimasi indikatif, belum diverifikasi terhadap listing terkini')
            ->assertSee('Total Biaya Efektif')
            ->assertDontSee('Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.')
            ->assertSee('Evaluasi Pembelian')
            ->assertSee('Saran Pembelian')
            ->assertSee('CF diagnosis:')
            ->assertSee('98%')
            ->assertSee('keyakinan diagnosis, bukan probabilitas kelayakan pembelian');

        $this->assertDatabaseHas('market_values', [
            'device_id' => $device->id,
            'storage_variant' => '512GB',
            'condition' => 'baik',
            'market_price_min' => 20000000,
            'market_price_max' => 24000000,
        ]);
        $this->assertDatabaseHas('market_values', [
            'device_id' => Device::query()->where('mobile_api_id', '10774')->value('id'),
            'storage_variant' => '512GB',
            'condition' => 'baik',
            'market_price_min' => 8000000,
            'market_price_max' => 11000000,
        ]);
        $this->assertDatabaseHas('market_values', [
            'device_id' => $device->id,
            'storage_variant' => '1TB',
            'condition' => 'baik',
            'market_price_min' => 23000000,
            'market_price_max' => 27000000,
        ]);
        $this->assertDatabaseHas('market_values', [
            'device_id' => Device::query()->where('mobile_api_id', '10774')->value('id'),
            'storage_variant' => '256GB',
            'condition' => 'baik',
            'market_price_min' => 7000000,
            'market_price_max' => 9000000,
        ]);
    }

    #[Test]
    public function market_value_seeder_uses_mobile_api_ids_and_preserves_other_market_values(): void
    {
        $deviceDefinitions = [
            ['753', 'iPhone 13'],
            ['754', 'iPhone 13 Pro Max'],
            ['756', 'iPhone 14'],
            ['757', 'iPhone 15'],
            ['38', 'iPhone 15 Pro Max'],
            ['11743', 'Galaxy S23'],
            ['576', 'Galaxy S24'],
            ['493', 'Redmi Note 13'],
            ['448', 'Reno11'],
            ['3051', 'V30'],
            ['unrelated-device', 'Unrelated device'],
        ];
        $devices = collect($deviceDefinitions)
            ->mapWithKeys(fn (array $definition): array => [
                $definition[0] => $this->createDevice($definition[0], $definition[1]),
            ]);
        $existingValue = $this->createMarketValue($devices['unrelated-device'], [
            'storage_variant' => '64GB',
            'condition' => 'baik',
            'source' => 'Existing source',
        ]);
        $legacyGalaxyS23Value = $this->createMarketValue($devices['11743'], [
            'storage_variant' => '8/256GB',
            'condition' => 'baik',
            'market_price_min' => '6000000.00',
            'market_price_max' => '6500000.00',
            'source' => 'Legacy Galaxy S23 storage format',
        ]);
        $legacyMarketValues = collect([
            '576' => ['8/256GB'],
            '493' => ['8/256GB'],
            '448' => ['8/256GB'],
        ])->flatMap(fn (array $storageVariants, string $mobileApiId) => collect($storageVariants)
            ->map(fn (string $storageVariant): MarketValue => $this->createMarketValue($devices[$mobileApiId], [
                'storage_variant' => $storageVariant,
                'condition' => 'baik',
                'source' => 'Legacy storage format',
            ])));

        $this->seed(MarketValueSeeder::class);

        $this->seed(MarketValueSeeder::class);

        $this->assertDatabaseCount('market_values', 10);
        $this->assertModelExists($existingValue);
        $canonicalMarketValues = [
            '576' => ['256GB', 7575000, 8800000],
            '493' => ['256GB', 2389500, 2950000],
            '448' => ['256GB', 3250000, 4500000],
        ];
        foreach ($canonicalMarketValues as $mobileApiId => [$storageVariant, $minimum, $maximum]) {
            $this->assertDatabaseHas('market_values', [
                'device_id' => $devices[$mobileApiId]->id,
                'storage_variant' => $storageVariant,
                'condition' => 'baik',
                'market_price_min' => $minimum,
                'market_price_max' => $maximum,
            ]);
        }
        $this->assertDatabaseHas('market_values', [
            'device_id' => $devices['754']->id,
            'storage_variant' => '128GB',
            'condition' => 'baik',
            'market_price_min' => 8400000,
            'market_price_max' => 10690000,
            'source' => 'Estimasi pasar; bukan harga transaksi pasti. Sumber: Shopee Indonesia',
        ]);
        $this->assertDatabaseHas('market_values', [
            'device_id' => $devices['11743']->id,
            'storage_variant' => '256GB',
            'condition' => 'baik',
            'market_price_min' => 6000000,
            'market_price_max' => 6500000,
            'source' => 'Estimasi pasar; bukan harga transaksi pasti. Sumber: OLX Indonesia',
        ]);
        $this->assertModelMissing($legacyGalaxyS23Value);
        $this->assertDatabaseMissing('market_values', [
            'device_id' => $devices['11743']->id,
            'storage_variant' => '8/256GB',
            'condition' => 'baik',
        ]);
        foreach ($legacyMarketValues as $legacyMarketValue) {
            $this->assertModelMissing($legacyMarketValue);
        }
        $this->assertSame(0, $devices['3051']->marketValues()->count());
        $this->assertDatabaseCount('market_values', 10);

        $duplicateGroups = MarketValue::query()
            ->select('device_id', 'storage_variant', 'condition')
            ->groupBy('device_id', 'storage_variant', 'condition')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $this->assertSame(0, $duplicateGroups);
    }

    #[Test]
    public function galaxy_s23_market_value_matches_capacity_storage_and_not_other_capacities(): void
    {
        $device = $this->createDevice('11743', 'Galaxy S23');
        $device->forceFill(['storage' => '128GB, 256GB, 512GB'])->save();

        $this->seed(MarketValueSeeder::class);

        $marketValue = app(MarketValueService::class)->forDevice($device, '256GB');
        $this->assertNotNull($marketValue);
        $this->assertSame('256GB', $marketValue->storage_variant);
        $this->assertSame('6000000.00', $marketValue->market_price_min);
        $this->assertSame('6500000.00', $marketValue->market_price_max);
        $this->assertNull(app(MarketValueService::class)->forDevice($device, '128GB'));
    }

    #[Test]
    public function android_and_iphone_result_pages_show_distinct_market_and_purchase_prices(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        $deviceDefinitions = [
            [
                'name' => 'Samsung Galaxy S24',
                'slug' => 'android-smartphone',
                'mobile_api_id' => 'result-samsung-s24',
                'market_min' => '7500000.00',
                'market_max' => '9500000.00',
            ],
            [
                'name' => 'iPhone 15',
                'slug' => 'iphone',
                'mobile_api_id' => 'result-iphone-15',
                'market_min' => '10500000.00',
                'market_max' => '13000000.00',
            ],
        ];

        foreach ($deviceDefinitions as $definition) {
            $device = $this->createDevice($definition['mobile_api_id'], $definition['name']);
            $this->createMarketValue($device, [
                'storage_variant' => '128 GB',
                'condition' => 'Bekas baik',
                'market_price_min' => $definition['market_min'],
                'market_price_max' => $definition['market_max'],
            ]);
            $deviceType = DeviceType::query()->where('slug', $definition['slug'])->firstOrFail();
            $session = AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'device_id' => $device->id,
                'purchase_price' => '2000000.00',
                'status' => 'completed',
            ]);
            AssessmentResult::query()->create([
                'assessment_session_id' => $session->id,
                'classification' => 'bersyarat',
                'contributing_factors' => [
                    ['fact_code' => 'battery_health_issue'],
                ],
            ]);

            $this->get(route('assessments.result', $session))
                ->assertOk()
                ->assertSee('Nilai Pasar')
                ->assertSee('Rp 2.000.000,00')
                ->assertSee('Rp '.number_format((float) $definition['market_min'], 2, ',', '.'))
                ->assertSee('Rp '.number_format((float) $definition['market_max'], 2, ',', '.'))
                ->assertSee('Rp 2.350.000,00 – Rp 2.700.000,00');
        }
    }

    #[Test]
    public function assessment_result_selects_market_value_for_its_saved_storage_variant(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $this->createDevice('device-before-market-test-1', 'Other device 1');
        $this->createDevice('device-before-market-test-2', 'Other device 2');
        $device = $this->createDevice('754', 'iPhone 13 Pro Max');

        $this->createMarketValue($device, [
            'storage_variant' => '128GB',
            'condition' => 'baik',
            'market_price_min' => '8400000.00',
            'market_price_max' => '10690000.00',
        ]);
        $this->createMarketValue($device, [
            'storage_variant' => '256GB',
            'condition' => 'baik',
            'market_price_min' => '12000000.00',
            'market_price_max' => '15000000.00',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'storage_variant' => '128GB',
            'purchase_price' => '8000000.00',
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $session->id,
            'classification' => 'bersyarat',
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Storage:')
            ->assertSee('128GB')
            ->assertSee('Rp 8.400.000,00 – Rp 10.690.000,00')
            ->assertDontSee('Rp 12.000.000,00 – Rp 15.000.000,00');
    }

    #[Test]
    public function assessment_result_matches_only_its_canonical_storage_variant(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice('576', 'Galaxy S24', ['storage' => '128GB, 256GB, 512GB']);
        $this->createMarketValue($device, [
            'storage_variant' => '256GB',
            'condition' => 'baik',
            'market_price_min' => '7575000.00',
            'market_price_max' => '8800000.00',
        ]);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $matchingSession = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'storage_variant' => '256GB',
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $matchingSession->id,
            'classification' => 'layak',
        ]);
        $nonMatchingSession = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'storage_variant' => '128GB',
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $nonMatchingSession->id,
            'classification' => 'layak',
        ]);

        $this->get(route('assessments.result', $matchingSession))
            ->assertOk()
            ->assertSee('256GB')
            ->assertSee('Rp 7.575.000,00 – Rp 8.800.000,00')
            ->assertDontSee('Nilai pasar belum tersedia');

        $this->get(route('assessments.result', $nonMatchingSession))
            ->assertOk()
            ->assertSee('128GB')
            ->assertSee('Belum tersedia untuk varian 128GB.')
            ->assertSee('Varian yang tersedia:')
            ->assertSee('256GB')
            ->assertSee('Rp 7.575.000,00 – Rp 8.800.000,00')
            ->assertSee('Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.');
    }

    #[Test]
    public function legacy_result_without_a_device_shows_market_value_unavailable(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $session->id,
            'classification' => 'layak',
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Nilai pasar belum tersedia');

        $this->assertNull($session->device_id);
        $this->assertNull($session->purchase_price);
    }

    #[Test]
    public function market_value_does_not_change_diagnosis_certainty_or_effective_cost(): void
    {
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        $device = $this->createDevice('invariance-device', 'Android test device');
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'purchase_price' => '2000000.00',
            'status' => 'in_progress',
        ]);

        foreach (Question::query()->with('options')->get() as $question) {
            $option = $question->options->firstWhere(
                'code',
                $question->code === 'battery_health' ? 'issue' : 'normal'
            );
            $session->answers()->create([
                'question_id' => $question->id,
                'question_option_id' => $option->id,
                'answer_state' => AssessmentAnswer::STATE_KNOWN,
                'evidence_source' => 'DIRECT_INSPECTION',
            ]);
        }

        $expertEvaluationService = app(ExpertEvaluationService::class);
        $initialResult = $expertEvaluationService->evaluate($session);
        $repairEstimates = app(RepairCostService::class)->estimatesFor($initialResult);
        $purchaseEvaluationService = app(PurchaseEvaluationService::class);
        $initialEffectiveCost = $purchaseEvaluationService->calculate(
            $session->purchase_price,
            $repairEstimates
        );

        $this->createMarketValue($device, [
            'market_price_min' => '7000000.00',
            'market_price_max' => '9000000.00',
        ]);

        $updatedResult = $expertEvaluationService->evaluate($session->fresh());
        $updatedRepairEstimates = app(RepairCostService::class)->estimatesFor($updatedResult);
        $updatedEffectiveCost = $purchaseEvaluationService->calculate(
            $session->purchase_price,
            $updatedRepairEstimates
        );

        $this->assertSame($initialResult->classification, $updatedResult->classification);
        $this->assertSame($initialResult->confidence_score, $updatedResult->confidence_score);
        $this->assertSame($initialResult->active_rules, $updatedResult->active_rules);
        $this->assertSame($initialEffectiveCost['effective_cost_min'], $updatedEffectiveCost['effective_cost_min']);
        $this->assertSame($initialEffectiveCost['effective_cost_max'], $updatedEffectiveCost['effective_cost_max']);
    }

    private function createDevice(string $mobileApiId, string $name, array $attributes = []): Device
    {
        return Device::query()->create($attributes + [
            'mobile_api_id' => $mobileApiId,
            'name' => $name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMarketValue(Device $device, array $attributes = []): MarketValue
    {
        return $device->marketValues()->create($attributes + [
            'market_price_min' => '7000000.00',
            'market_price_max' => '9000000.00',
        ]);
    }
}
