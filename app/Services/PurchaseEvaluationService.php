<?php

namespace App\Services;

use App\Models\RepairCost;
use Illuminate\Database\Eloquent\Collection;

class PurchaseEvaluationService
{
    /**
     * @param  array{has_issues: bool, has_unavailable: bool, entries: Collection<int, RepairCost>}  $repairCostEstimates
     * @return array{
     *     purchase_price: ?string,
     *     repair_cost_min: ?string,
     *     repair_cost_max: ?string,
     *     effective_cost_min: ?string,
     *     effective_cost_max: ?string,
     *     has_purchase_price: bool,
     *     has_repair_estimate: bool,
     *     has_repairable_issues: bool,
     *     repair_estimate_status: 'available'|'unavailable'|'no_repairable_issues'
     * }
     */
    public function calculate(?string $purchasePrice, array $repairCostEstimates): array
    {
        $hasRepairableIssues = $repairCostEstimates['has_issues'];
        $hasRepairEstimate = ! $repairCostEstimates['has_unavailable'];
        $repairEstimateStatus = match (true) {
            $repairCostEstimates['has_unavailable'] => 'unavailable',
            ! $hasRepairableIssues => 'no_repairable_issues',
            default => 'available',
        };
        $repairCostMin = null;
        $repairCostMax = null;

        if ($hasRepairEstimate) {
            $repairCostMin = $this->amountFromMinorUnits(
                $repairCostEstimates['entries']->sum(
                    fn (RepairCost $repairCost): int => $this->minorUnits($repairCost->minimum_cost)
                )
            );
            $repairCostMax = $this->amountFromMinorUnits(
                $repairCostEstimates['entries']->sum(
                    fn (RepairCost $repairCost): int => $this->minorUnits($repairCost->maximum_cost)
                )
            );
        }

        $hasPurchasePrice = $purchasePrice !== null;
        $effectiveCostMin = $hasPurchasePrice && $hasRepairEstimate
            ? $this->amountFromMinorUnits(
                $this->minorUnits($purchasePrice) + $this->minorUnits($repairCostMin)
            )
            : null;
        $effectiveCostMax = $hasPurchasePrice && $hasRepairEstimate
            ? $this->amountFromMinorUnits(
                $this->minorUnits($purchasePrice) + $this->minorUnits($repairCostMax)
            )
            : null;

        return [
            'purchase_price' => $purchasePrice,
            'repair_cost_min' => $repairCostMin,
            'repair_cost_max' => $repairCostMax,
            'effective_cost_min' => $effectiveCostMin,
            'effective_cost_max' => $effectiveCostMax,
            'has_purchase_price' => $hasPurchasePrice,
            'has_repair_estimate' => $hasRepairEstimate,
            'has_repairable_issues' => $hasRepairableIssues,
            'repair_estimate_status' => $repairEstimateStatus,
        ];
    }

    private function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function amountFromMinorUnits(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
