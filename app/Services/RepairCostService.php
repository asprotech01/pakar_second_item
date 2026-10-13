<?php

namespace App\Services;

use App\Models\AssessmentResult;
use App\Models\RepairCost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RepairCostService
{
    /**
     * @return array{has_issues: bool, has_unavailable: bool, entries: Collection<int, RepairCost>}
     */
    public function estimatesFor(AssessmentResult $result): array
    {
        $diagnosisCodes = collect($result->contributing_factors ?? [])
            ->pluck('fact_code')
            ->filter(fn ($code) => is_string($code) && Str::endsWith($code, '_issue'))
            ->unique()
            ->values();

        $costs = RepairCost::query()
            ->whereIn('diagnosis_code', $diagnosisCodes)
            ->orderBy('issue_name')
            ->orderBy('diagnosis_code')
            ->get();
        $matchedCodes = $costs->pluck('diagnosis_code');

        return [
            'has_issues' => $diagnosisCodes->isNotEmpty(),
            'has_unavailable' => $diagnosisCodes->diff($matchedCodes)->isNotEmpty(),
            'entries' => $costs,
        ];
    }
}
