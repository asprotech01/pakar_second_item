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
use App\Services\PurchaseEvaluationService;
use App\Services\PurchaseRecommendationService;
use App\Services\RepairCostService;
use Database\Seeders\ExpertSystemSeeder;
use Database\Seeders\RepairCostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurchaseRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_technically_eligible_device_below_the_market_range_is_recommended(): void
    {
        $evaluation = $this->evaluate(
            'layak',
            '2000000.00',
            '2400000.00',
            $this->marketValue('2600000.00', '3000000.00')
        );

        $this->assertSame('LAYAK', $evaluation['recommendation']);
        $this->assertSame('Kondisi perangkat layak dan seluruh rentang total biaya efektif berada di bawah rentang nilai pasar.', $evaluation['reason']);
        $this->assertTrue($evaluation['economic_available']);
    }

    #[Test]
    public function a_conditional_diagnosis_remains_conditional_when_price_ranges_overlap(): void
    {
        $evaluation = $this->evaluate(
            'bersyarat',
            '2700000.00',
            '3100000.00',
            $this->marketValue('2600000.00', '3000000.00')
        );

        $this->assertSame('BERSYARAT', $evaluation['recommendation']);
        $this->assertStringContainsString('Kondisi perangkat memerlukan perhatian', $evaluation['reason']);
    }

    #[Test]
    public function a_not_recommended_diagnosis_results_in_not_eligible_regardless_of_price(): void
    {
        $evaluation = $this->evaluate(
            'tidak_direkomendasikan',
            null,
            null,
            null
        );

        $this->assertSame('TIDAK LAYAK', $evaluation['recommendation']);
        $this->assertFalse($evaluation['economic_available']);
        $this->assertSame('tidak_direkomendasikan', $evaluation['diagnosis']);
    }

    #[Test]
    public function an_attractive_after_repair_evaluation_can_override_a_conditional_technical_classification(): void
    {
        $evaluation = $this->evaluate(
            'bersyarat',
            '1500000.00',
            '1800000.00',
            $this->marketValue('2600000.00', '3000000.00')
        );

        $this->assertSame('LAYAK', $evaluation['recommendation']);
        $this->assertSame('ATTRACTIVE_AFTER_REPAIR', $evaluation['decision_status']);
        $this->assertTrue($evaluation['economic_available']);
    }

    #[Test]
    public function effective_cost_clearly_above_the_market_range_is_not_eligible(): void
    {
        $evaluation = $this->evaluate(
            'layak',
            '3000000.00',
            '3500000.00',
            $this->marketValue('2400000.00', '2800000.00')
        );

        $this->assertSame('TIDAK LAYAK', $evaluation['recommendation']);
        $this->assertSame('Bahkan total biaya efektif minimum melebihi batas atas rentang nilai pasar.', $evaluation['reason']);
    }

    #[Test]
    public function overlapping_or_boundary_touching_ranges_are_not_given_an_aggressive_recommendation(): void
    {
        $evaluation = $this->evaluate(
            'layak',
            '2500000.00',
            '2600000.00',
            $this->marketValue('2600000.00', '3000000.00')
        );

        $this->assertSame('BERSYARAT', $evaluation['recommendation']);
        $this->assertStringContainsString('overlap atau berdekatan', $evaluation['reason']);
    }

    #[Test]
    public function incomplete_economic_data_does_not_produce_an_economic_recommendation(): void
    {
        $assessmentResult = new AssessmentResult([
            'classification' => 'layak',
            'confidence_score' => 78.0,
        ]);
        $service = app(PurchaseRecommendationService::class);
        $completeMarketValue = $this->marketValue('2600000.00', '3000000.00');

        $missingPurchasePrice = $service->evaluate(
            $assessmentResult,
            $this->effectiveCost(false, true, null, null),
            $completeMarketValue
        );
        $missingMarketValue = $service->evaluate(
            $assessmentResult,
            $this->effectiveCost(true, true, '2300000.00', '2500000.00'),
            null
        );
        $missingRepairEstimate = $service->evaluate(
            $assessmentResult,
            $this->effectiveCost(true, false, null, null),
            $completeMarketValue
        );

        foreach ([$missingPurchasePrice, $missingMarketValue, $missingRepairEstimate] as $evaluation) {
            $this->assertNull($evaluation['recommendation']);
            $this->assertFalse($evaluation['economic_available']);
            $this->assertSame('Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.', $evaluation['reason']);
        }
    }

    #[Test]
    public function android_and_iphone_result_pages_show_the_same_separate_purchase_evaluation(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);

        $deviceDefinitions = [
            ['android-smartphone', 'samsung-evaluation', 'Samsung Galaxy S24'],
            ['iphone', 'iphone-evaluation', 'iPhone 15'],
        ];

        foreach ($deviceDefinitions as [$deviceTypeSlug, $mobileApiId, $deviceName]) {
            $device = Device::query()->create([
                'mobile_api_id' => $mobileApiId,
                'name' => $deviceName,
                'manufacturer_name' => $deviceTypeSlug === 'iphone' ? 'Apple' : 'Samsung',
            ]);
            $device->marketValues()->create([
                'market_price_min' => '2600000.00',
                'market_price_max' => '3000000.00',
            ]);
            $deviceType = DeviceType::query()->where('slug', $deviceTypeSlug)->firstOrFail();
            $session = AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'device_id' => $device->id,
                'purchase_price' => '2000000.00',
                'status' => 'completed',
            ]);
            AssessmentResult::query()->create([
                'assessment_session_id' => $session->id,
                'classification' => 'layak',
                'confidence_score' => 78.0,
                'contributing_factors' => [
                    ['fact_code' => 'battery_health_issue'],
                ],
            ]);

            $this->get(route('assessments.result', $session))
                ->assertOk()
                ->assertSee('Evaluasi Pembelian')
                ->assertSee('BERSYARAT')
                ->assertSee('Diagnosis teknis:')
                ->assertSee('CF diagnosis:')
                ->assertSee('keyakinan diagnosis, bukan probabilitas kelayakan pembelian')
                ->assertSee('Saran Pembelian')
                ->assertSee('PERTIMBANGKAN SEBELUM MEMBELI')
                ->assertSee('Rentang total biaya efektif dan nilai pasar overlap atau berdekatan')
                ->assertSee('Total Biaya Efektif')
                ->assertSee('Nilai Pasar');
        }
    }

    #[Test]
    public function result_page_presents_purchase_suggestions_consistent_with_the_system_decision(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $cases = [
            [
                'name' => 'below-market',
                'diagnosis' => 'layak',
                'purchase_price' => '2000000.00',
                'market_min' => '3000000.00',
                'market_max' => '3500000.00',
                'status' => 'LAYAK',
                'suggestion' => 'DIREKOMENDASIKAN UNTUK DIBELI',
            ],
            [
                'name' => 'conditional-complete',
                'diagnosis' => 'bersyarat',
                'purchase_price' => '2000000.00',
                'market_min' => '2600000.00',
                'market_max' => '3000000.00',
                'status' => 'BERSYARAT',
                'suggestion' => 'PERTIMBANGKAN SEBELUM MEMBELI',
            ],
            [
                'name' => 'missing-market',
                'diagnosis' => 'bersyarat',
                'purchase_price' => '2000000.00',
                'market_min' => null,
                'market_max' => null,
                'status' => 'BERSYARAT',
                'suggestion' => 'LENGKAPI INFORMASI SEBELUM MEMBELI',
            ],
            [
                'name' => 'above-market',
                'diagnosis' => 'layak',
                'purchase_price' => '3000000.00',
                'market_min' => '2400000.00',
                'market_max' => '2800000.00',
                'status' => 'TIDAK LAYAK',
                'suggestion' => 'SEBAIKNYA TIDAK DIBELI',
            ],
        ];

        foreach ($cases as $case) {
            $device = Device::query()->create([
                'mobile_api_id' => 'suggestion-'.$case['name'],
                'name' => 'Suggestion test device',
            ]);

            if ($case['market_min'] !== null && $case['market_max'] !== null) {
                $device->marketValues()->create([
                    'market_price_min' => $case['market_min'],
                    'market_price_max' => $case['market_max'],
                ]);
            }

            $session = AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'device_id' => $device->id,
                'purchase_price' => $case['purchase_price'],
                'status' => 'completed',
            ]);
            AssessmentResult::query()->create([
                'assessment_session_id' => $session->id,
                'classification' => $case['diagnosis'],
                'contributing_factors' => [
                    ['fact_code' => 'battery_health_issue'],
                ],
            ]);

            $response = $this->get(route('assessments.result', $session));

            $response
                ->assertSee($case['status'])
                ->assertSee('Saran Pembelian')
                ->assertSee($case['suggestion']);

            if ($case['name'] === 'conditional-complete') {
                $response
                    ->assertSee('Kondisi perangkat memerlukan perhatian');
            }

            if ($case['name'] === 'above-market') {
                $response
                    ->assertSee('Bahkan total biaya efektif minimum melebihi batas atas rentang nilai pasar.')
                    ->assertDontSee('DIREKOMENDASIKAN UNTUK DIBELI');
            }
        }
    }

    #[Test]
    public function legacy_session_without_device_or_price_shows_incomplete_economics_without_changing_diagnosis(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'status' => 'completed',
        ]);
        $assessmentResult = AssessmentResult::query()->create([
            'assessment_session_id' => $session->id,
            'classification' => 'layak',
            'confidence_score' => 90.0,
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.')
            ->assertSee('Layak dipertimbangkan')
            ->assertSee('CF diagnosis:');

        $this->assertNull($session->device_id);
        $this->assertNull($session->purchase_price);
        $this->assertSame('layak', $assessmentResult->fresh()->classification);
        $this->assertSame(90.0, $assessmentResult->fresh()->confidence_score);
    }

    #[Test]
    public function market_value_does_not_change_diagnosis_certainty_factor_or_effective_cost(): void
    {
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
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
        $assessmentResult = $expertEvaluationService->evaluate($session);
        $repairCostEstimates = app(RepairCostService::class)->estimatesFor($assessmentResult);
        $effectiveCostService = app(PurchaseEvaluationService::class);
        $effectiveCostBefore = $effectiveCostService->calculate($session->purchase_price, $repairCostEstimates);
        $diagnosisBefore = $assessmentResult->classification;
        $certaintyFactorBefore = $assessmentResult->confidence_score;
        $device = Device::query()->create([
            'mobile_api_id' => 'evaluation-invariance',
            'name' => 'Evaluation invariance device',
        ]);
        $device->marketValues()->create([
            'market_price_min' => '2600000.00',
            'market_price_max' => '3000000.00',
        ]);

        $purchaseRecommendation = app(PurchaseRecommendationService::class)->evaluate(
            $assessmentResult,
            $effectiveCostBefore,
            $device->marketValues()->first()
        );
        $effectiveCostAfter = $effectiveCostService->calculate($session->purchase_price, $repairCostEstimates);

        $this->assertSame($diagnosisBefore, $assessmentResult->fresh()->classification);
        $this->assertSame($certaintyFactorBefore, $assessmentResult->fresh()->confidence_score);
        $this->assertSame($effectiveCostBefore['effective_cost_min'], $effectiveCostAfter['effective_cost_min']);
        $this->assertSame($effectiveCostBefore['effective_cost_max'], $effectiveCostAfter['effective_cost_max']);
        $this->assertSame('BERSYARAT', $purchaseRecommendation['recommendation']);
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function evaluate(
        string $diagnosis,
        ?string $effectiveCostMin,
        ?string $effectiveCostMax,
        ?MarketValue $marketValue
    ): array {
        $assessmentResult = new AssessmentResult([
            'classification' => $diagnosis,
            'confidence_score' => 78.0,
        ]);

        return app(PurchaseRecommendationService::class)->evaluate(
            $assessmentResult,
            $this->effectiveCost(
                $effectiveCostMin !== null && $effectiveCostMax !== null,
                $effectiveCostMin !== null && $effectiveCostMax !== null,
                $effectiveCostMin,
                $effectiveCostMax
            ),
            $marketValue
        );
    }

    /**
     * @return array{has_purchase_price: bool, has_repair_estimate: bool, effective_cost_min: ?string, effective_cost_max: ?string}
     */
    private function effectiveCost(
        bool $hasPurchasePrice,
        bool $hasRepairEstimate,
        ?string $minimum,
        ?string $maximum
    ): array {
        return [
            'has_purchase_price' => $hasPurchasePrice,
            'has_repair_estimate' => $hasRepairEstimate,
            'effective_cost_min' => $minimum,
            'effective_cost_max' => $maximum,
        ];
    }

    private function marketValue(string $minimum, string $maximum): MarketValue
    {
        return new MarketValue([
            'market_price_min' => $minimum,
            'market_price_max' => $maximum,
        ]);
    }
}
