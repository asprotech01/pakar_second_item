<?php

namespace Tests\Feature\Services;

use App\Models\AssessmentResult;
use App\Models\AssessmentSession;
use App\Models\DeviceType;
use App\Models\RepairCost;
use App\Models\User;
use App\Services\RepairCostService;
use Database\Seeders\ExpertSystemSeeder;
use Database\Seeders\RepairCostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepairCostServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function estimates_are_loaded_by_stable_diagnosis_code_with_original_amounts(): void
    {
        $this->seed(RepairCostSeeder::class);
        $result = new AssessmentResult([
            'contributing_factors' => [
                ['fact_code' => 'battery_health_issue'],
                ['fact_code' => 'battery_swelling_issue'],
                ['fact_code' => 'device_needs_inspection'],
            ],
        ]);

        $estimates = app(RepairCostService::class)->estimatesFor($result);

        $this->assertTrue($estimates['has_issues']);
        $this->assertFalse($estimates['has_unavailable']);
        $this->assertCount(2, $estimates['entries']);
        $this->assertSame(
            ['battery_health_issue', 'battery_swelling_issue'],
            $estimates['entries']->pluck('diagnosis_code')->all()
        );
        $this->assertSame('350000.00', $estimates['entries']->first()->minimum_cost);
        $this->assertSame('700000.00', $estimates['entries']->first()->maximum_cost);
    }

    #[Test]
    public function all_issue_estimates_are_retained_even_when_their_display_names_match(): void
    {
        $this->seed(RepairCostSeeder::class);
        $result = new AssessmentResult([
            'contributing_factors' => [
                ['fact_code' => 'physical_condition_issue'],
                ['fact_code' => 'liquid_damage_issue'],
                ['fact_code' => 'battery_health_issue'],
                ['fact_code' => 'camera_condition_issue'],
                ['fact_code' => 'audio_condition_issue'],
                ['fact_code' => 'biometric_condition_issue'],
                ['fact_code' => 'button_condition_issue'],
            ],
        ]);

        $estimates = app(RepairCostService::class)->estimatesFor($result);

        $this->assertCount(7, $estimates['entries']);
        $this->assertSame(
            [
                'audio_condition_issue',
                'battery_health_issue',
                'biometric_condition_issue',
                'button_condition_issue',
                'camera_condition_issue',
                'liquid_damage_issue',
                'physical_condition_issue',
            ],
            $estimates['entries']->pluck('diagnosis_code')->sort()->values()->all()
        );
        $this->assertSame(
            3,
            $estimates['entries']->where('issue_name', 'Komponen teknis')->count()
        );
    }

    #[Test]
    public function missing_estimates_are_reported_without_a_fallback_cost(): void
    {
        $result = new AssessmentResult([
            'contributing_factors' => [
                ['fact_code' => 'unmapped_component_issue'],
            ],
        ]);

        $estimates = app(RepairCostService::class)->estimatesFor($result);

        $this->assertTrue($estimates['has_issues']);
        $this->assertTrue($estimates['has_unavailable']);
        $this->assertCount(0, $estimates['entries']);
    }

    #[Test]
    public function result_pages_use_the_same_estimate_for_android_and_iphone_sessions(): void
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
                ->assertSee('Estimasi perbaikan')
                ->assertSee('Baterai')
                ->assertSee('Rp350.000 – Rp700.000');
        }

        $this->assertCount(2, $deviceTypes);
    }

    #[Test]
    public function result_page_shows_unavailable_status_for_unmapped_issue_and_legacy_session(): void
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
            'classification' => 'bersyarat',
            'contributing_factors' => [
                ['fact_code' => 'unmapped_component_issue'],
            ],
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Estimasi biaya perbaikan belum tersedia')
            ->assertDontSee('Rp150.000 – Rp500.000');

        $this->assertNull($session->purchase_price);
        $this->assertDatabaseMissing('repair_costs', ['diagnosis_code' => 'unmapped_component_issue']);
    }

    #[Test]
    public function result_blade_displays_a_missing_estimate_when_a_seeded_cost_is_removed(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed([
            ExpertSystemSeeder::class,
            RepairCostSeeder::class,
        ]);
        RepairCost::query()->where('diagnosis_code', 'battery_health_issue')->delete();
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();
        $session = AssessmentSession::query()->create([
            'device_type_id' => $deviceType->id,
            'status' => 'completed',
        ]);
        AssessmentResult::query()->create([
            'assessment_session_id' => $session->id,
            'classification' => 'bersyarat',
            'recommendations' => [['title' => 'Baterai bermasalah', 'body' => 'Issue']],
            'contributing_factors' => [
                ['fact_code' => 'battery_health_issue'],
            ],
        ]);

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Estimasi biaya perbaikan belum tersedia')
            ->assertDontSee('Rp350.000 – Rp700.000');
    }
}
