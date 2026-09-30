<?php

namespace Tests\Feature;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSession;
use App\Models\DeviceType;
use App\Models\Question;
use App\Services\ExpertSystem\ExpertEvaluationService;
use Database\Seeders\ExpertSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpertEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpertSystemSeeder::class);
    }

    #[Test]
    public function home_page_displays_the_assessment_dashboard_instead_of_the_laravel_welcome_screen(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Keputusan baik')
            ->assertSee('Smartphone Android')
            ->assertSee('12 cek')
            ->assertSee('iPhone');
    }

    #[Test]
    public function browser_assessment_flow_saves_answers_and_displays_explainable_result(): void
    {
        $deviceType = DeviceType::where('slug', 'android-smartphone')->firstOrFail();

        $this->post(route('assessments.store'), [
            'device_type_id' => $deviceType->id,
            'device_label' => 'Galaxy A54',
        ])->assertRedirect();

        $session = AssessmentSession::latest()->firstOrFail();
        $this->get(route('assessments.show', $session))
            ->assertOk()
            ->assertSee('Belum diketahui')
            ->assertSee('Sumber bukti');

        $answers = [];
        foreach (Question::with('options')->get() as $question) {
            $option = $question->options->firstWhere('code', 'normal');
            $answers[$question->id] = [
                'selection' => 'option:'.$option->id,
                'source' => 'DIRECT_INSPECTION',
            ];
        }

        $this->post(route('assessments.evaluate', $session), ['answers' => $answers])
            ->assertRedirect(route('assessments.result', $session));

        $this->get(route('assessments.result', $session))
            ->assertOk()
            ->assertSee('Galaxy A54')
            ->assertSee('Layak dipertimbangkan')
            ->assertSee('DATA COMPLETENESS')
            ->assertSee('all_required_checks_normal');
    }

    #[Test]
    public function unknown_answer_lowers_completeness_and_never_triggers_the_all_clear_rule(): void
    {
        $session = $this->makeSession();
        $unknownQuestion = Question::where('code', 'activation_lock')->firstOrFail();

        foreach (Question::all() as $question) {
            if ($question->is($unknownQuestion)) {
                $session->answers()->create([
                    'question_id' => $question->id,
                    'answer_state' => AssessmentAnswer::STATE_UNKNOWN,
                    'evidence_source' => 'UNKNOWN',
                ]);

                continue;
            }

            $option = $question->options()->where('code', 'normal')->firstOrFail();
            $session->answers()->create([
                'question_id' => $question->id,
                'question_option_id' => $option->id,
                'answer_state' => AssessmentAnswer::STATE_KNOWN,
                'evidence_source' => 'DIRECT_INSPECTION',
            ]);
        }

        $result = app(ExpertEvaluationService::class)->evaluate($session);

        $this->assertSame(91.67, $result->data_completeness);
        $this->assertSame('unverified', $result->classification);
        $this->assertFalse($result->threshold_met);
        $this->assertSame('UNKNOWN', $result->unverified_data[0]['state']);
        $this->assertSame([], $result->active_rules);
    }

    #[Test]
    public function complete_normal_evidence_produces_an_explainable_viable_result(): void
    {
        $session = $this->makeSession();

        foreach (Question::all() as $question) {
            $option = $question->options()->where('code', 'normal')->firstOrFail();
            $session->answers()->create([
                'question_id' => $question->id,
                'question_option_id' => $option->id,
                'answer_state' => AssessmentAnswer::STATE_KNOWN,
                'evidence_source' => 'DIRECT_INSPECTION',
            ]);
        }

        $result = app(ExpertEvaluationService::class)->evaluate($session);

        $this->assertSame('layak', $result->classification);
        $this->assertSame(90.0, $result->confidence_score);
        $this->assertSame(100.0, $result->data_completeness);
        $this->assertSame('low', $result->risk_level);
        $this->assertTrue($result->threshold_met);
        $this->assertSame('all_required_checks_normal', $result->active_rules[0]['code']);
    }

    #[Test]
    public function unknown_evidence_source_is_unverified_and_never_contributes_a_fact(): void
    {
        $session = $this->makeSession();
        $unknownSourceQuestion = Question::where('code', 'activation_lock')->firstOrFail();

        foreach (Question::all() as $question) {
            $option = $question->options()->where('code', 'normal')->firstOrFail();
            $session->answers()->create([
                'question_id' => $question->id,
                'question_option_id' => $option->id,
                'answer_state' => AssessmentAnswer::STATE_KNOWN,
                'evidence_source' => $question->is($unknownSourceQuestion) ? 'UNKNOWN' : 'DIRECT_INSPECTION',
            ]);
        }

        $result = app(ExpertEvaluationService::class)->evaluate($session);

        $this->assertSame(91.67, $result->data_completeness);
        $this->assertSame([], $result->active_rules);
        $this->assertSame('source_unknown', $result->unverified_data[0]['reason']);
        $this->assertNotContains(
            'activation_lock_normal',
            array_column($result->contributing_factors, 'fact_code')
        );
    }

    private function makeSession(): AssessmentSession
    {
        return AssessmentSession::create([
            'device_type_id' => DeviceType::where('slug', 'android-smartphone')->value('id'),
            'status' => 'in_progress',
        ]);
    }
}
