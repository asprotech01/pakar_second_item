<?php

namespace App\Services\ExpertSystem;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentResult;
use App\Models\AssessmentSession;
use App\Models\Fact;
use App\Models\Question;
use App\Models\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpertEvaluationService
{
    public function __construct(
        private readonly ForwardChainingEngine $forwardChaining,
        private readonly CertaintyFactorEngine $certaintyFactors,
        private readonly RecommendationService $recommendations
    ) {}

    public function evaluate(AssessmentSession $session): AssessmentResult
    {
        $session->loadMissing('deviceType');

        $questions = Question::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('device_type_id')
                ->orWhere('device_type_id', $session->device_type_id))
            ->with('inspectionCategory')
            ->orderBy('inspection_category_id')
            ->orderBy('sort_order')
            ->get();

        $answers = $session->answers()
            ->with(['option.fact'])
            ->get()
            ->keyBy('question_id');

        $initialFacts = $this->initialFacts($questions, $answers);
        $rules = $this->activeRules();
        $chaining = $this->forwardChaining->evaluate($initialFacts, $rules);
        $certainty = $this->certaintyFactors->evaluate($initialFacts, $chaining['fired_rules']);
        $knownFacts = $this->loadFactMetadata($chaining['known_facts']);
        $notEvaluableRules = $chaining['not_evaluable_rules'] ?? [];

        $completeness = $this->dataCompleteness($questions, $answers);
        $conclusions = $this->conclusions($chaining['fired_rules'], $certainty['certainty_by_fact']);
        $selectedConclusion = $conclusions[0] ?? null;
        $confidence = max(0, (float) ($selectedConclusion['certainty'] ?? 0)) * 100;
        $risk = $this->riskLevel($chaining['fired_rules'], $conclusions);
        $confidenceThreshold = (float) $session->deviceType->confidence_threshold;
        $completenessThreshold = (float) $session->deviceType->completeness_threshold;
        $thresholdMet = $confidence / 100 >= $confidenceThreshold
            && $completeness / 100 >= $completenessThreshold;

        $activeRules = array_map(fn (array $rule) => [
            'id' => $rule['id'],
            'code' => $rule['code'],
            'name' => $rule['name'],
            'description' => $rule['description'],
            'conclusion' => $rule['conclusion'],
            'classification' => $rule['classification'],
            'risk_level' => $rule['risk_level'],
            'certainty_factor' => $this->ruleCertainty($certainty['rule_contributions'], $rule['code']),
            'experts' => $rule['experts'],
        ], $chaining['fired_rules']);

        $explanation = $this->contributingFactors($questions, $answers, $initialFacts, $knownFacts);
        $recommendationData = $this->recommendations->recommend(
            $session,
            $questions,
            $answers->values(),
            $activeRules,
            $knownFacts
        );

        return DB::transaction(function () use (
            $session,
            $selectedConclusion,
            $confidence,
            $completeness,
            $risk,
            $thresholdMet,
            $confidenceThreshold,
            $completenessThreshold,
            $activeRules,
            $explanation,
            $recommendationData,
            $notEvaluableRules
        ) {
            $result = AssessmentResult::updateOrCreate(
                ['assessment_session_id' => $session->id],
                [
                    'primary_conclusion_fact_id' => $selectedConclusion['id'] ?? null,
                    'classification' => $selectedConclusion['classification'] ?? 'unverified',
                    'confidence_score' => round($confidence, 2),
                    'data_completeness' => round($completeness, 2),
                    'risk_level' => $risk,
                    'threshold_met' => $thresholdMet,
                    'confidence_threshold' => $confidenceThreshold,
                    'completeness_threshold' => $completenessThreshold,
                    'active_rules' => $activeRules,
                    'contributing_factors' => $explanation,
                    'unverified_data' => $recommendationData['unverified_data'],
                    'recommendations' => $recommendationData['recommendations'],
                    'not_evaluable_rules' => $notEvaluableRules,
                    'evaluated_at' => now(),
                ]
            );

            $session->forceFill(['status' => 'evaluated', 'completed_at' => now()])->save();

            return $result;
        });
    }

    private function initialFacts(Collection $questions, Collection $answers): array
    {
        $facts = [];

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);
            if ($answer === null || $answer->answer_state !== 'KNOWN' || $answer->evidence_source === 'UNKNOWN') {
                continue;
            }

            if ($answer->observed_value !== null && $this->shouldTreatAsDirectFact($question, $answer)) {
                $value = $answer->observed_value;
                if (is_numeric((string) $value)) {
                    $value = (float) $value;
                }

                $facts[$question->code] = [
                    'certainty' => max(0, min(1, (float) $answer->certainty_factor)),
                    'value' => $value,
                    'id' => null,
                ];
            }

            $option = $answer?->option;
            if ($option === null
                || $option->question_id !== $question->id
                || $option->fact === null
                || ! $option->fact->is_active) {
                continue;
            }

            $factCode = $option->fact->code;
            $certainty = max(0, min(1, $answer->certainty_factor * $option->certainty_factor));
            if ($certainty === 0.0) {
                continue;
            }

            $facts[$factCode] ??= [
                'certainty' => 0.0,
                'value' => $option->value,
                'id' => $option->fact->id,
            ];
            $facts[$factCode]['certainty'] = $this->certaintyFactors->combine([
                $facts[$factCode]['certainty'],
                $certainty,
            ]);
        }

        return $facts;
    }

    private function shouldTreatAsDirectFact(Question $question, AssessmentAnswer $answer): bool
    {
        if ($answer->question_option_id !== null) {
            return false;
        }

        if (is_string($answer->observed_value) && trim($answer->observed_value) === '') {
            return false;
        }

        $inputType = strtoupper((string) ($question->input_type ?? 'single_choice'));

        return in_array($inputType, ['NUMBER', 'NUMERIC', 'TEXT', 'BOOLEAN'], true)
            || is_numeric((string) $answer->observed_value);
    }

    private function activeRules(): array
    {
        return Rule::query()
            ->where('is_active', true)
            ->whereHas('conclusion', fn ($query) => $query->where('is_active', true))
            ->with([
                'conditions.fact' => fn ($query) => $query->where('is_active', true),
                'conclusion',
                'experts' => fn ($query) => $query->where('experts.is_active', true),
            ])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Rule $rule) => $rule->conditions->isNotEmpty()
                && $rule->conditions->every(fn ($condition) => $condition->fact !== null))
            ->map(function (Rule $rule) {
                $experts = $rule->experts
                    ->filter(fn ($expert) => $expert->pivot->certainty_factor !== null)
                    ->values();
                $expertCertainties = $experts
                    ->map(fn ($expert) => (float) $expert->pivot->certainty_factor)
                    ->all();
                $certaintyFactor = $expertCertainties === []
                    ? $rule->certainty_factor
                    : $this->certaintyFactors->combine($expertCertainties);

                return [
                    'id' => $rule->id,
                    'code' => $rule->code,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'conclusion' => $rule->conclusion->code,
                    'certainty_factor' => $certaintyFactor,
                    'classification' => $rule->classification ?? $rule->conclusion->classification,
                    'risk_level' => $rule->risk_level ?? $rule->conclusion->risk_level,
                    'experts' => $experts->map(fn ($expert) => [
                        'name' => $expert->name,
                        'certainty_factor' => (float) $expert->pivot->certainty_factor,
                    ])->all(),
                    'conditions' => $rule->conditions->map(fn ($condition) => [
                        'fact' => $condition->fact->code,
                        'operator' => $condition->operator,
                        'expected_value' => $condition->expected_value,
                        'group_number' => $condition->group_number,
                        'logical_operator' => $condition->logical_operator,
                    ])->all(),
                ];
            })
            ->all();
    }

    private function dataCompleteness(Collection $questions, Collection $answers): float
    {
        $requiredQuestions = $questions->where('is_required', true)->filter(
            fn (Question $question) => $answers->get($question->id)?->answer_state !== AssessmentAnswer::STATE_NOT_APPLICABLE
        );
        $totalWeight = (float) $requiredQuestions->sum('completeness_weight');

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $verifiedWeight = $requiredQuestions->sum(function (Question $question) use ($answers) {
            $answer = $answers->get($question->id);

            return $answer !== null
                && $answer->answer_state === 'KNOWN'
                && $answer->evidence_source !== 'UNKNOWN'
                && $answer->option !== null
                && $answer->option->question_id === $question->id
                && $answer->option->fact !== null
                    ? $question->completeness_weight
                    : 0;
        });

        return round(($verifiedWeight / $totalWeight) * 100, 2);
    }

    private function loadFactMetadata(array $knownFacts): array
    {
        $codes = array_keys($knownFacts);
        $facts = Fact::query()->whereIn('code', $codes)->get()->keyBy('code');

        foreach ($knownFacts as $code => $evidence) {
            if ($facts->has($code)) {
                $knownFacts[$code]['id'] = $facts[$code]->id;
                $knownFacts[$code]['name'] = $facts[$code]->name;
                $knownFacts[$code]['is_conclusion'] = $facts[$code]->is_conclusion;
                $knownFacts[$code]['classification'] = $facts[$code]->classification;
                $knownFacts[$code]['risk_level'] = $facts[$code]->risk_level;
            }
        }

        return $knownFacts;
    }

    private function conclusions(array $firedRules, array $certaintyByFact): array
    {
        $codes = collect($firedRules)->pluck('conclusion')->unique()->all();

        return Fact::query()
            ->whereIn('code', $codes)
            ->where('is_conclusion', true)
            ->get()
            ->map(fn (Fact $fact) => [
                'id' => $fact->id,
                'code' => $fact->code,
                'classification' => $fact->classification,
                'risk_level' => $fact->risk_level,
                'certainty' => $certaintyByFact[$fact->code] ?? 0,
            ])
            ->sortByDesc('certainty')
            ->values()
            ->all();
    }

    private function riskLevel(array $firedRules, array $conclusions): string
    {
        $rank = ['unknown' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $levels = array_merge(
            array_column($firedRules, 'risk_level'),
            array_column($conclusions, 'risk_level')
        );
        $levels = array_filter($levels, fn ($level) => $level !== null && isset($rank[$level]));

        if ($levels === []) {
            return 'unknown';
        }

        usort($levels, fn ($left, $right) => $rank[$right] <=> $rank[$left]);

        return $levels[0];
    }

    private function contributingFactors(
        Collection $questions,
        Collection $answers,
        array $initialFacts,
        array $knownFacts
    ): array {
        $factors = [];

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);
            $option = $answer?->option;

            if ($answer === null
                || $answer->answer_state !== 'KNOWN'
                || $answer->evidence_source === 'UNKNOWN'
                || $option?->question_id !== $question->id
                || $option?->fact === null
                || ! array_key_exists($option->fact->code, $initialFacts)) {
                continue;
            }

            $factors[] = [
                'question_code' => $question->code,
                'question' => $question->prompt,
                'answer' => $option->label,
                'fact_code' => $option->fact->code,
                'fact' => $option->fact->name,
                'certainty_factor' => $initialFacts[$option->fact->code]['certainty'] ?? 0,
                'source' => $answer->evidence_source,
            ];
        }

        foreach ($knownFacts as $code => $fact) {
            if (($fact['is_conclusion'] ?? false) && isset($fact['certainty'])) {
                $factors[] = [
                    'fact_code' => $code,
                    'fact' => $fact['name'] ?? $code,
                    'certainty_factor' => $fact['certainty'],
                    'source' => 'RULE_INFERENCE',
                ];
            }
        }

        return $factors;
    }

    private function ruleCertainty(array $contributions, string $ruleCode): float
    {
        foreach ($contributions as $contribution) {
            if ($contribution['code'] === $ruleCode) {
                return $contribution['certainty_factor'];
            }
        }

        return 0.0;
    }
}
