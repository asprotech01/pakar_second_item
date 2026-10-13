<?php

namespace App\Services;

use App\Models\AssessmentResult;
use App\Models\MarketValue;

class PurchaseRecommendationService
{
    /**
     * @param  array{
     *     has_purchase_price: bool,
     *     has_repair_estimate: bool,
     *     effective_cost_min: ?string,
     *     effective_cost_max: ?string,
     *     repair_estimate_status?: string,
     * }  $effectiveCost
     * @return array{
     *     recommendation: ?string,
     *     reason: string,
     *     diagnosis: ?string,
     *     certainty_factor: ?float,
     *     economic_available: bool,
     *     effective_cost_min: ?string,
     *     effective_cost_max: ?string,
     *     market_value_min: ?string,
     *     market_value_max: ?string,
     *     decision_status: string,
     *     technical_blocker: bool,
     *     market_value_fresh: bool,
     * }
     */
    public function evaluate(
        AssessmentResult $assessmentResult,
        array $effectiveCost,
        ?MarketValue $marketValue
    ): array {
        $effectiveCostMin = $effectiveCost['effective_cost_min'];
        $effectiveCostMax = $effectiveCost['effective_cost_max'];
        $marketValueMin = $marketValue?->market_price_min;
        $marketValueMax = $marketValue?->market_price_max;

        $missingEconomicData = $this->missingEconomicData($effectiveCost, $marketValue);
        $economicAvailable = empty($missingEconomicData);
        $technicalBlocker = $this->hasTechnicalBlocker($assessmentResult);
        $marketValueFresh = $this->isMarketValueFresh($marketValue);

        $decisionStatus = 'INSUFFICIENT_ECONOMIC_DATA';
        $recommendation = null;
        $reason = 'Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.';

        if ($assessmentResult->classification === 'tidak_direkomendasikan') {
            $recommendation = 'TIDAK LAYAK';
            $reason = 'Diagnosis teknis tidak merekomendasikan perangkat ini; evaluasi harga tidak mengubah kondisi tersebut.';
            $decisionStatus = 'NOT_ECONOMICAL';
        } elseif ($economicAvailable) {
            $decisionStatus = $this->economicDecisionStatus(
                $effectiveCostMin,
                $effectiveCostMax,
                $marketValueMin,
                $marketValueMax
            );

            if ($technicalBlocker) {
                $recommendation = 'TIDAK LAYAK';
                $reason = 'Kendala teknis atau keamanan masih menghalangi pembelian meskipun evaluasi ekonomi bisa terlihat menarik.';
            } elseif ($decisionStatus === 'ATTRACTIVE_AFTER_REPAIR') {
                $recommendation = 'LAYAK';
                $reason = 'Kondisi perangkat layak dan seluruh rentang total biaya efektif berada di bawah rentang nilai pasar.';
            } elseif ($decisionStatus === 'POTENTIALLY_WORTHWHILE') {
                $recommendation = 'BERSYARAT';
                $reason = $assessmentResult->classification === 'bersyarat'
                    ? 'Kondisi perangkat memerlukan perhatian; temuan perlu ditangani dan diverifikasi sebelum transaksi.'
                    : 'Total biaya efektif masih di bawah nilai pasar, tetapi margin penghematan belum cukup untuk rekomendasi kuat.';
            } elseif ($decisionStatus === 'NEGOTIATE_OR_REVIEW') {
                $recommendation = 'BERSYARAT';
                $reason = $assessmentResult->classification === 'bersyarat'
                    ? 'Kondisi perangkat memerlukan perhatian; temuan perlu ditangani dan diverifikasi sebelum transaksi.'
                    : 'Rentang total biaya efektif dan nilai pasar overlap atau berdekatan; bandingkan kondisi unit dan pertimbangkan negosiasi.';
            } elseif ($decisionStatus === 'NOT_ECONOMICAL') {
                $recommendation = 'TIDAK LAYAK';
                $reason = 'Bahkan total biaya efektif minimum melebihi batas atas rentang nilai pasar.';
            }
        } elseif (! empty($missingEconomicData)) {
            $reason = $this->missingEconomicReason($missingEconomicData);
            if (in_array($assessmentResult->classification, ['bersyarat', 'unverified'], true) || ($assessmentResult->classification !== null && $assessmentResult->classification !== 'layak')) {
                $recommendation = 'BERSYARAT';
                $reason = 'Kondisi perangkat memerlukan perhatian; data ekonomi belum lengkap untuk perbandingan harga.';
            }
        }

        return [
            'recommendation' => $recommendation,
            'reason' => $reason,
            'diagnosis' => $assessmentResult->classification,
            'certainty_factor' => $assessmentResult->confidence_score === null
                ? null
                : (float) $assessmentResult->confidence_score,
            'economic_available' => $economicAvailable,
            'effective_cost_min' => $effectiveCostMin,
            'effective_cost_max' => $effectiveCostMax,
            'market_value_min' => $marketValueMin,
            'market_value_max' => $marketValueMax,
            'decision_status' => $decisionStatus,
            'technical_blocker' => $technicalBlocker,
            'market_value_fresh' => $marketValueFresh,
            'missing_economic_data' => $missingEconomicData,
        ];
    }

    private function missingEconomicData(array $effectiveCost, ?MarketValue $marketValue): array
    {
        $missing = [];

        if (! $effectiveCost['has_purchase_price']) {
            $missing[] = 'purchase_price';
        }

        if (! $effectiveCost['has_repair_estimate']) {
            $missing[] = 'repair_estimate';
        }

        if ($effectiveCost['effective_cost_min'] === null || $effectiveCost['effective_cost_max'] === null) {
            $missing[] = 'effective_cost';
        }

        if ($marketValue === null || $marketValue->market_price_min === null || $marketValue->market_price_max === null) {
            $missing[] = 'market_value';
        }

        if ($marketValue !== null && $marketValue->observed_at !== null && $this->isStaleMarketValue($marketValue->observed_at)) {
            $missing[] = 'market_value_stale';
        }

        return $missing;
    }

    private function missingEconomicReason(array $missingEconomicData): string
    {
        return 'Evaluasi ekonomi belum dapat dilakukan karena data harga belum lengkap.';
    }

    private function economicDecisionStatus(
        ?string $effectiveCostMin,
        ?string $effectiveCostMax,
        ?string $marketValueMin,
        ?string $marketValueMax
    ): string {
        if ($effectiveCostMin === null || $effectiveCostMax === null || $marketValueMin === null || $marketValueMax === null) {
            return 'INSUFFICIENT_ECONOMIC_DATA';
        }

        $effectiveCostMinUnits = $this->minorUnits($effectiveCostMin);
        $effectiveCostMaxUnits = $this->minorUnits($effectiveCostMax);
        $marketValueMinUnits = $this->minorUnits($marketValueMin);
        $marketValueMaxUnits = $this->minorUnits($marketValueMax);

        if ($effectiveCostMinUnits > $marketValueMaxUnits) {
            return 'NOT_ECONOMICAL';
        }

        $targetSavingsMargin = (float) config('purchase_economics.target_savings_margin', 0.05);
        $attractiveThreshold = (int) round($marketValueMinUnits * (1 - $targetSavingsMargin));

        if ($effectiveCostMaxUnits <= $attractiveThreshold) {
            return 'ATTRACTIVE_AFTER_REPAIR';
        }

        if ($effectiveCostMaxUnits <= $marketValueMinUnits) {
            if ($effectiveCostMaxUnits >= $marketValueMinUnits) {
                return 'NEGOTIATE_OR_REVIEW';
            }

            return 'POTENTIALLY_WORTHWHILE';
        }

        return 'NEGOTIATE_OR_REVIEW';
    }

    private function hasTechnicalBlocker(AssessmentResult $assessmentResult): bool
    {
        $factCodes = collect($assessmentResult->contributing_factors ?? [])
            ->pluck('fact_code')
            ->filter(fn ($code) => is_string($code))
            ->all();

        return collect($factCodes)->contains(fn (string $code): bool => in_array($code, config('purchase_economics.hard_blocker_fact_codes', []), true))
            || in_array((string) ($assessmentResult->risk_level ?? ''), config('purchase_economics.high_risk_levels', []), true);
    }

    private function isMarketValueFresh(?MarketValue $marketValue): bool
    {
        if ($marketValue === null || $marketValue->observed_at === null) {
            return true;
        }

        return ! $this->isStaleMarketValue($marketValue->observed_at);
    }

    private function isStaleMarketValue(\DateTimeInterface $observedAt): bool
    {
        $maxAgeDays = (int) config('purchase_economics.market_value_max_age_days', 180);

        return now()->diffInDays($observedAt) > $maxAgeDays;
    }

    private function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
