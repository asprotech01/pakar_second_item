<?php

namespace App\Services\ExpertSystem;

class CertaintyFactorEngine
{
    public function combine(array $evidence): float
    {
        $combined = 0.0;

        foreach ($evidence as $certainty) {
            $certainty = $this->clamp((float) $certainty);

            if ($combined >= 0 && $certainty >= 0) {
                $combined += $certainty * (1 - $combined);
            } elseif ($combined <= 0 && $certainty <= 0) {
                $combined += $certainty * (1 + $combined);
            } else {
                $denominator = 1 - min(abs($combined), abs($certainty));
                $combined = $denominator === 0.0 ? 0.0 : ($combined + $certainty) / $denominator;
            }
        }

        return $this->clamp($combined);
    }

    public function evaluate(array $initialEvidence, array $firedRules): array
    {
        $certaintyByFact = [];
        foreach ($initialEvidence as $factCode => $evidence) {
            $certainty = is_array($evidence) ? ($evidence['certainty'] ?? 0) : $evidence;
            $certaintyByFact[$factCode] = $this->clamp((float) $certainty);
        }

        $ruleContributions = [];
        $contributionsByConclusion = [];

        foreach ($firedRules as $rule) {
            $conditionCertainties = [];
            foreach ($rule['conditions'] ?? [] as $condition) {
                $conditionCertainties[] = $certaintyByFact[$condition['fact'] ?? ''] ?? 0.0;
            }

            $conclusion = $rule['conclusion'] ?? null;
            if (! is_string($conclusion) || $conclusion === '' || $conditionCertainties === []) {
                continue;
            }

            $ruleCertainty = $this->clamp((float) ($rule['certainty_factor'] ?? 1));
            $contribution = $this->applyRule($ruleCertainty, $conditionCertainties);
            $contributionsByConclusion[$conclusion][] = $contribution;
            $certaintyByFact[$conclusion] = $this->combine($contributionsByConclusion[$conclusion]);
            $ruleContributions[] = [
                'code' => $rule['code'] ?? null,
                'conclusion' => $conclusion,
                'certainty_factor' => $contribution,
            ];
        }

        return [
            'certainty_by_fact' => $certaintyByFact,
            'rule_contributions' => $ruleContributions,
        ];
    }

    public function applyRule(float $ruleCertainty, array $conditionCertainties): float
    {
        if ($conditionCertainties === []) {
            return 0.0;
        }

        return $this->clamp($this->clamp($ruleCertainty) * min(array_map(
            fn ($certainty) => $this->clamp((float) $certainty),
            $conditionCertainties
        )));
    }

    private function clamp(float $certainty): float
    {
        return max(-1.0, min(1.0, $certainty));
    }
}
