<?php

namespace App\Services\ExpertSystem;

class ForwardChainingEngine
{
    public function evaluate(array $initialFacts, iterable $rules): array
    {
        $knownFacts = $initialFacts;
        $rules = is_array($rules) ? array_values($rules) : iterator_to_array($rules);
        $firedRules = [];
        $notEvaluableRules = [];
        $firedRuleKeys = [];

        do {
            $changed = false;

            foreach ($rules as $rule) {
                $ruleKey = (string) ($rule['code'] ?? $rule['id'] ?? count($firedRuleKeys));
                $conditions = $rule['conditions'] ?? [];

                if (isset($firedRuleKeys[$ruleKey]) || $conditions === []) {
                    continue;
                }

                $ruleEvaluation = $this->evaluateRuleConditions($conditions, $knownFacts);
                if ($ruleEvaluation['status'] === 'NOT_EVALUABLE') {
                    $notEvaluableRules[] = [
                        'code' => $rule['code'] ?? $ruleKey,
                        'rule_id' => $rule['id'] ?? null,
                        'status' => 'NOT_EVALUABLE',
                        'missing_facts' => $ruleEvaluation['missing_facts'],
                        'reason' => 'Required evidence missing',
                    ];

                    continue;
                }

                if ($ruleEvaluation['status'] !== 'TRIGGERED') {
                    continue;
                }

                $firedRuleKeys[$ruleKey] = true;
                $rule['matched_condition_groups'] = $ruleEvaluation['matched_groups'];
                $firedRules[] = $rule;
                $conclusion = $rule['conclusion'] ?? null;

                if (is_string($conclusion) && $conclusion !== '' && ! array_key_exists($conclusion, $knownFacts)) {
                    $knownFacts[$conclusion] = ['value' => true];
                }

                $changed = true;
            }
        } while ($changed);

        return [
            'known_facts' => $knownFacts,
            'fired_rules' => $firedRules,
            'inferred_facts' => array_values(array_diff(array_keys($knownFacts), array_keys($initialFacts))),
            'not_evaluable_rules' => $notEvaluableRules,
        ];
    }

    private function evaluateRuleConditions(array $conditions, array $facts): array
    {
        $groupResults = [];
        $groupedConditions = $this->groupConditions($conditions);

        foreach ($groupedConditions as $groupKey => $groupConditions) {
            $groupMatches = null;
            $missingFacts = [];

            foreach ($groupConditions as $condition) {
                $factCode = $condition['fact'] ?? null;
                $missing = is_string($factCode) && ! array_key_exists($factCode, $facts);
                if ($missing) {
                    $missingFacts[] = $factCode;
                }

                $matches = $this->conditionMatches($condition, $facts);
                if ($groupMatches === null) {
                    $groupMatches = $matches;
                    continue;
                }

                $groupMatches = strtoupper((string) ($condition['logical_operator'] ?? 'AND')) === 'OR'
                    ? $groupMatches || $matches
                    : $groupMatches && $matches;
            }

            $groupResults[$groupKey] = [
                'status' => $missingFacts !== [] && $groupMatches !== true ? 'NOT_EVALUABLE' : ($groupMatches === true ? 'TRIGGERED' : 'NOT_TRIGGERED'),
                'matched' => $groupMatches === true,
                'missing_facts' => array_values(array_unique(array_filter($missingFacts))),
            ];
        }

        if ($groupResults === []) {
            return ['status' => 'NOT_EVALUABLE', 'missing_facts' => [], 'matched_groups' => []];
        }

        $matchedGroups = [];
        $hasTriggered = false;
        $hasNotEvaluable = false;
        foreach ($groupResults as $groupKey => $groupResult) {
            if ($groupResult['matched']) {
                $matchedGroups[] = $groupedConditions[$groupKey];
                $hasTriggered = true;
            }

            if ($groupResult['status'] === 'NOT_EVALUABLE') {
                $hasNotEvaluable = true;
            }
        }

        $status = 'NOT_TRIGGERED';
        if ($hasTriggered) {
            $status = 'TRIGGERED';
        } elseif ($hasNotEvaluable) {
            $status = 'NOT_EVALUABLE';
        }

        $missingFacts = [];
        foreach ($groupResults as $groupResult) {
            $missingFacts = array_values(array_unique(array_merge($missingFacts, $groupResult['missing_facts'])));
        }

        return [
            'status' => $status,
            'missing_facts' => $missingFacts,
            'matched_groups' => $matchedGroups,
        ];
    }

    private function groupConditions(array $conditions): array
    {
        $groups = [];
        foreach ($conditions as $condition) {
            $groups[(int) ($condition['group_number'] ?? 0)][] = $condition;
        }

        return $groups;
    }

    private function matchedConditionGroups(array $conditions, array $facts): array
    {
        $groups = $this->groupConditions($conditions);

        $matchedGroups = [];
        foreach ($groups as $groupConditions) {
            $groupMatches = null;
            foreach ($groupConditions as $condition) {
                $matches = $this->conditionMatches($condition, $facts);
                if ($groupMatches === null) {
                    $groupMatches = $matches;

                    continue;
                }

                $groupMatches = strtoupper((string) ($condition['logical_operator'] ?? 'AND')) === 'OR'
                    ? $groupMatches || $matches
                    : $groupMatches && $matches;
            }

            if ($groupMatches === true) {
                $matchedGroups[] = $groupConditions;
            }
        }

        return $matchedGroups;
    }

    private function conditionMatches(array $condition, array $facts): bool
    {
        $factCode = $condition['fact'] ?? null;
        $operator = strtoupper((string) ($condition['operator'] ?? 'PRESENT'));
        $isKnown = is_string($factCode) && array_key_exists($factCode, $facts);
        $fact = $isKnown ? $facts[$factCode] : null;
        $value = is_array($fact) ? ($fact['value'] ?? null) : $fact;
        $expected = $condition['expected_value'] ?? null;

        if (in_array($operator, ['GREATER_THAN', 'GT', '>', 'GREATER_THAN_OR_EQUAL', 'GTE', '>=', 'LESS_THAN', 'LT', '<', 'LESS_THAN_OR_EQUAL', 'LTE', '<='], true)) {
            if (! $isKnown || ! is_numeric($value) || ! is_numeric($expected)) {
                return false;
            }

            return match ($operator) {
                'GREATER_THAN', 'GT', '>' => (float) $value > (float) $expected,
                'GREATER_THAN_OR_EQUAL', 'GTE', '>=' => (float) $value >= (float) $expected,
                'LESS_THAN', 'LT', '<' => (float) $value < (float) $expected,
                'LESS_THAN_OR_EQUAL', 'LTE', '<=' => (float) $value <= (float) $expected,
            };
        }

        return match ($operator) {
            'PRESENT' => $isKnown,
            'ABSENT' => $isKnown && $value === false,
            'EQUALS' => $isKnown && $value === $expected,
            'NOT_EQUALS' => $isKnown && $value !== $expected,
            default => false,
        };
    }
}
