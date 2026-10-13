<?php

namespace Tests\Feature\Services;

use App\Models\AssessmentResult;
use App\Models\AssessmentSession;
use App\Models\DeviceType;
use App\Models\User;
use App\Services\PurchaseEvaluationService;
use App\Services\RepairCostService;
use Database\Seeders\ExpertSystemSeeder;
use Database\Seeders\RepairCostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PurchaseEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function effective_cost_is_calculated_from_purchase_price_and_one_repair_estimate(): void
    {
        $this->seed(RepairCostSeeder::class);

        $evaluation = $this->calculate('2000000.00', ['battery_health_issue']);

        $this->assertSame('2000000.00', $evaluation['purchase_price']);
        $this->assertSame('350000.00', $evaluation['repair_cost_min']);
        $this->assertSame('700000.00', $evaluation['repair_cost_max']);
        $this->assertSame('2350000.00', $evaluation['effective_cost_min']);
        $this->assertSame('2700000.00', $evaluation['effective_cost_max']);
        $this->assertTrue($evaluation['has_purchase_price']);
        $this->assertTrue($evaluation['has_repair_estimate']);
    }

    #[Test]
    public function effective_cost_sums_all_available_repair_estimates(): void
    {
        $this->seed(RepairCostSeeder::class);

        $evaluation = $this->calculate('2500000.00', [
            'battery_health_issue',
            'display_condition_issue',
        ]);

        $this->assertSame('900000.00', $evaluation['repair_cost_min']);
        $this->assertSame('2300000.00', $evaluation['repair_cost_max']);
        $this->assertSame('3400000.00', $evaluation['effective_cost_min']);
        $this->assertSame('4800000.00', $evaluation['effective_cost_max']);
    }

    #[Test]
    public function effective_cost_includes_all_seven_issue_estimates_with_matching_display_names(): void
    {
        $this->seed(RepairCostSeeder::class);

        $evaluation = $this->calculate('2000000.00', [
            'physical_condition_issue',
            'liquid_damage_issue',
            'battery_health_issue',
            'camera_condition_issue',
            'audio_condition_issue',
            'biometric_condition_issue',
            'button_condition_issue',
        ]);

        $this->assertSame('1420000.00', $evaluation['repair_cost_min']);
        $this->assertSame('3950000.00', $evaluation['repair_cost_max']);
        $this->assertSame('3420000.00', $evaluation['effective_cost_min']);
        $this->assertSame('5950000.00', $evaluation['effective_cost_max']);
    }

    #[Test]
    public function effective_cost_is_not_calculated_when_purchase_price_is_missing(): void
    {
        $this->seed(RepairCostSeeder::class);

        $evaluation = $this->calculate(null, ['battery_health_issue']);

        $this->assertNull($evaluation['purchase_price']);
        $this->assertSame('350000.00', $evaluation['repair_cost_min']);
        $this->assertSame('700000.00', $evaluation['repair_cost_max']);
        $this->assertNull($evaluation['effective_cost_min']);
        $this->assertNull($evaluation['effective_cost_max']);
        $this->assertFalse($evaluation['has_purchase_price']);
        $this->assertTrue($evaluation['has_repair_estimate']);
    }

    #[Test]
    public function missing_repair_estimates_do_not_create_default_costs_or_effective_totals(): void
    {
        $evaluation = $this->calculate('2000000.00', ['unmapped_component_issue']);

        $this->assertNull($evaluation['repair_cost_min']);
        $this->assertNull($evaluation['repair_cost_max']);
        $this->assertNull($evaluation['effective_cost_min']);
        $this->assertNull($evaluation['effective_cost_max']);
        $this->assertFalse($evaluation['has_repair_estimate']);
        $this->assertSame('unavailable', $evaluation['repair_estimate_status']);
    }

    #[Test]
    public function no_repairable_issues_are_distinguished_from_missing_estimates(): void
    {
        $evaluation = $this->calculate('2000000.00', []);

        $this->assertSame('0.00', $evaluation['repair_cost_min']);
        $this->assertSame('0.00', $evaluation['repair_cost_max']);
        $this->assertSame('2000000.00', $evaluation['effective_cost_min']);
        $this->assertSame('2000000.00', $evaluation['effective_cost_max']);
        $this->assertTrue($evaluation['has_repair_estimate']);
        $this->assertFalse($evaluation['has_repairable_issues']);
        $this->assertSame('no_repairable_issues', $evaluation['repair_estimate_status']);
    }

    #[Test]
    public function android_and_iphone_result_pages_show_the_same_effective_cost_calculation(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        $deviceTypes = DeviceType::query()
            ->whereIn('slug', ['android-smartphone', 'iphone'])
            ->get();

        foreach ($deviceTypes as $deviceType) {
            $session = AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'purchase_price' => '2500000.00',
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
                ->assertSee('Harga Beli')
                ->assertSee('Rp 2.500.000,00')
                ->assertSee('Estimasi Total Perbaikan')
                ->assertSee('Rp 350.000,00 – Rp 700.000,00')
                ->assertSee('Total Biaya Efektif')
                ->assertSee('Rp 2.850.000,00 – Rp 3.200.000,00');
        }

        $this->assertCount(2, $deviceTypes);
    }

    #[Test]
    public function legacy_result_page_explains_when_purchase_price_is_missing(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
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
            ->assertSee('Total biaya efektif belum dapat dihitung karena harga beli belum tersedia.')
            ->assertSee('Rp 350.000,00 – Rp 700.000,00');

        $this->assertNull($session->purchase_price);
    }

    #[Test]
    public function result_page_does_not_display_partial_effective_cost_when_a_repair_estimate_is_missing(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'purchase_price' => '2000000.00',
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $session->id,
            'classification' => 'bersyarat',
            'contributing_factors' => [
                ['fact_code' => 'unmapped_component_issue'],
            ],
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Estimasi biaya perbaikan belum tersedia')
            ->assertSee('Total biaya efektif belum dapat dihitung karena estimasi biaya perbaikan belum tersedia.')
            ->assertDontSee('Rp 2.000.000,00 – Rp');
    }

    /**
     * @param  array<int, string>  $diagnosisCodes
     * @return array<string, bool|string|null>
     */
    private function calculate(?string $purchasePrice, array $diagnosisCodes): array
    {
        $result = new AssessmentResult([
            'contributing_factors' => array_map(
                fn (string $diagnosisCode): array => ['fact_code' => $diagnosisCode],
                $diagnosisCodes
            ),
        ]);
        $repairCostEstimates = app(RepairCostService::class)->estimatesFor($result);

        return app(PurchaseEvaluationService::class)->calculate($purchasePrice, $repairCostEstimates);
    }
}
