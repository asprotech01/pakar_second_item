<?php

namespace App\Services\ExpertSystem;

class ForwardChainingEngine
{
    public function evaluate(array $initialFacts, iterable $rules): array
    {
        $knownFacts = $initialFacts;
        $rules = is_array($rules) ? array_values($rules) : iterator_to_array($rules);
        $firedRules = [];
        $firedRuleKeys = [];

        do {
            $changed = false;

            foreach ($rules as $rule) {
                $ruleKey = (string) ($rule['code'] ?? $rule['id'] ?? count($firedRuleKeys));
                $conditions = $rule['conditions'] ?? [];

                if (isset($firedRuleKeys[$ruleKey]) || $conditions === []) {
                    continue;
                }

                if (! $this->conditionsMatch($conditions, $knownFacts)) {
                    continue;
                }

                $firedRuleKeys[$ruleKey] = true;
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
        ];
    }

    private function conditionsMatch(array $conditions, array $facts): bool
    {
        foreach ($conditions as $condition) {
            $factCode = $condition['fact'] ?? null;
            $operator = strtoupper((string) ($condition['operator'] ?? 'PRESENT'));
            $isKnown = is_string($factCode) && array_key_exists($factCode, $facts);
            $fact = $isKnown ? $facts[$factCode] : null;
            $value = is_array($fact) ? ($fact['value'] ?? null) : $fact;

            $matches = match ($operator) {
                'PRESENT' => $isKnown,
                'ABSENT' => $isKnown && $value === false,
                'EQUALS' => $isKnown && $value === ($condition['expected_value'] ?? null),
                'NOT_EQUALS' => $isKnown && $value !== ($condition['expected_value'] ?? null),
                default => false,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
