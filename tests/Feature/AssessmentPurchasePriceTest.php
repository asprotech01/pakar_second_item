<?php

namespace Tests\Feature;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSession;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Question;
use App\Models\User;
use App\Services\ExpertSystem\ExpertEvaluationService;
use Database\Seeders\ExpertSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentPurchasePriceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function android_assessment_saves_and_displays_a_valid_purchase_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice('android-price-1', 'Samsung Galaxy S24');
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'purchase_price' => '2500000.00',
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertDatabaseHas('assessment_sessions', [
            'id' => $session->id,
            'device_id' => $device->id,
            'purchase_price' => 2500000,
        ]);
        $this->assertSame('2500000.00', $session->fresh()->purchase_price);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('Harga Beli')
            ->assertSee('Rp 2.500.000,00');
    }

    #[Test]
    public function iphone_assessment_saves_and_displays_a_valid_purchase_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice('iphone-price-1', 'iPhone 15');
        $deviceType = DeviceType::query()->where('slug', 'iphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_id' => $device->id,
            'purchase_price' => '1299999.99',
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertDatabaseHas('assessment_sessions', [
            'id' => $session->id,
            'device_id' => $device->id,
            'purchase_price' => 1299999.99,
        ]);
        $this->assertSame('1299999.99', $session->fresh()->purchase_price);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('iPhone 15')
            ->assertSee('Harga Beli')
            ->assertSee('Rp 1.299.999,99');
    }

    #[Test]
    public function assessment_forms_include_an_optional_purchase_price_input(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $device = $this->createDevice('catalog-price-form', 'Catalog phone');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Harga beli')
            ->assertSee('name="purchase_price"', false)
            ->assertSee('name="device_id"', false)
            ->assertDontSee('name="device_label"', false);

        $this->withSession(['selected_device_id' => $device->id])
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Harga Beli')
            ->assertSee('name="purchase_price"', false);
    }

    #[Test]
    public function assessment_can_be_started_and_opened_without_a_purchase_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $response = $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_label' => 'Older assessment without price',
        ]);

        $session = AssessmentSession::query()->latest()->firstOrFail();
        $response->assertRedirect(route('assessments.show', $session));
        $this->assertNull($session->purchase_price);

        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('Older assessment without price')
            ->assertDontSee('Rp 0,00');
    }

    #[Test]
    #[DataProvider('invalidPurchasePrices')]
    public function non_positive_or_non_numeric_purchase_prices_are_rejected(string $purchasePrice): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();

        $this->from(route('dashboard'))
            ->post(route('assessments.store'), [
                'device_type_id' => $deviceType->id,
                'purchase_price' => $purchasePrice,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('purchase_price');

        $this->assertDatabaseCount('assessment_sessions', 0);
    }

    public static function invalidPurchasePrices(): array
    {
        return [
            'negative amount' => ['-1'],
            'zero amount' => ['0'],
            'non-numeric amount' => ['not-a-price'],
        ];
    }

    #[Test]
    public function purchase_price_does_not_change_the_expert_diagnosis(): void
    {
        $this->seed(ExpertSystemSeeder::class);
        $deviceType = DeviceType::query()->where('slug', 'android-smartphone')->firstOrFail();
        $questions = Question::query()->with('options')->get();
        $sessions = collect([
            AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'status' => 'in_progress',
            ]),
            AssessmentSession::query()->create([
                'device_type_id' => $deviceType->id,
                'purchase_price' => '9876543.21',
                'status' => 'in_progress',
            ]),
        ]);

        foreach ($sessions as $session) {
            foreach ($questions as $question) {
                $normalOption = $question->options->firstWhere('code', 'normal');

                $session->answers()->create([
                    'question_id' => $question->id,
                    'question_option_id' => $normalOption->id,
                    'answer_state' => AssessmentAnswer::STATE_KNOWN,
                    'evidence_source' => 'DIRECT_INSPECTION',
                ]);
            }
        }

        $evaluationService = app(ExpertEvaluationService::class);
        $withoutPrice = $evaluationService->evaluate($sessions[0]);
        $withPrice = $evaluationService->evaluate($sessions[1]);

        $this->assertSame($withoutPrice->classification, $withPrice->classification);
        $this->assertSame($withoutPrice->confidence_score, $withPrice->confidence_score);
        $this->assertSame($withoutPrice->data_completeness, $withPrice->data_completeness);
        $this->assertSame($withoutPrice->active_rules, $withPrice->active_rules);
    }

    private function createDevice(string $mobileApiId, string $name): Device
    {
        return Device::query()->create([
            'mobile_api_id' => $mobileApiId,
            'name' => $name,
            'manufacturer_name' => str_contains($name, 'iPhone') ? 'Apple' : 'Samsung',
        ]);
    }
}
