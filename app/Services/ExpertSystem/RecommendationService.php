<?php

namespace App\Services\ExpertSystem;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSession;
use App\Models\Question;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function recommend(
        AssessmentSession $session,
        Collection $questions,
        Collection $answers,
        array $activeRules,
        array $knownFacts
    ): array {
        $answersByQuestion = $answers->keyBy('question_id');
        $unverified = [];
        $unverifiedQuestionIds = [];

        foreach ($questions as $question) {
            $answer = $answersByQuestion->get($question->id);

            if ($answer === null) {
                if ($question->is_required) {
                    $unverified[] = $this->unverifiedQuestion($question, null, 'not_answered');
                    $unverifiedQuestionIds[] = $question->id;
                }

                continue;
            }

            if ($answer->answer_state === AssessmentAnswer::STATE_NOT_APPLICABLE) {
                continue;
            }

            if ($answer->answer_state === AssessmentAnswer::STATE_NOT_TESTED) {
                $unverified[] = $this->unverifiedQuestion($question, $answer, 'not_tested');
                $unverifiedQuestionIds[] = $question->id;
            } elseif ($answer->answer_state === AssessmentAnswer::STATE_UNKNOWN) {
                $unverified[] = $this->unverifiedQuestion($question, $answer, 'unknown');
                $unverifiedQuestionIds[] = $question->id;
            } elseif ($answer->evidence_source === 'UNKNOWN') {
                $unverified[] = $this->unverifiedQuestion($question, $answer, 'source_unknown');
                $unverifiedQuestionIds[] = $question->id;
            } elseif ($answer->option === null
                || $answer->option->question_id !== $question->id
                || $answer->option->fact === null) {
                $unverified[] = $this->unverifiedQuestion($question, $answer, 'answer_without_fact');
                $unverifiedQuestionIds[] = $question->id;
            }
        }

        $ruleIds = collect($activeRules)->pluck('id')->filter()->all();
        $factIds = collect(array_keys($knownFacts))
            ->map(fn ($code) => $knownFacts[$code]['id'] ?? null)
            ->filter()
            ->all();

        $recommendations = Recommendation::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('device_type_id')
                ->orWhere('device_type_id', $session->device_type_id))
            ->where(function ($query) use ($unverifiedQuestionIds, $ruleIds, $factIds) {
                if ($unverifiedQuestionIds !== []) {
                    $query->whereIn('question_id', $unverifiedQuestionIds);
                }

                if ($ruleIds !== []) {
                    $method = $unverifiedQuestionIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('rule_id', $ruleIds);
                }

                if ($factIds !== []) {
                    $method = $unverifiedQuestionIds === [] && $ruleIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('fact_id', $factIds);
                }
            })
            ->with('inspectionCategory:id,name')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (Recommendation $recommendation) => [
                'id' => $recommendation->id,
                'title' => $recommendation->title,
                'body' => $recommendation->body,
                'category' => $recommendation->inspectionCategory?->name,
                'priority' => $recommendation->priority,
            ])
            ->all();

        return ['unverified_data' => $unverified, 'recommendations' => $recommendations];
    }

    private function unverifiedQuestion(Question $question, ?AssessmentAnswer $answer, string $reason): array
    {
        return [
            'question_id' => $question->id,
            'question_code' => $question->code,
            'question' => $question->prompt,
            'state' => $answer?->answer_state ?? 'MISSING',
            'source' => $answer?->evidence_source ?? 'UNKNOWN',
            'reason' => $reason,
        ];
    }
}
