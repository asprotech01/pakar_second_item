<?php

namespace App\Services;

use App\Models\Device;
use App\Models\MarketValue;

class MarketValueService
{
    public function forDevice(
        Device $device,
        ?string $storageVariant = null,
        ?string $condition = null
    ): ?MarketValue {
        $marketValues = $device->marketValues()
            ->when(
                $storageVariant !== null,
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('storage_variant', $storageVariant)
                    ->orWhereNull('storage_variant'))
            )
            ->when(
                $condition !== null,
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('condition', $condition)
                    ->orWhereNull('condition'))
            )
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();

        return $marketValues
            ->sort(function (MarketValue $left, MarketValue $right) use ($storageVariant, $condition): int {
                $leftScore = $this->matchScore($left, $storageVariant, $condition);
                $rightScore = $this->matchScore($right, $storageVariant, $condition);

                if ($leftScore !== $rightScore) {
                    return $rightScore <=> $leftScore;
                }

                $leftObservedAt = $left->observed_at?->getTimestamp() ?? 0;
                $rightObservedAt = $right->observed_at?->getTimestamp() ?? 0;

                return ($rightObservedAt <=> $leftObservedAt) ?: ($right->id <=> $left->id);
            })
            ->first();
    }

    private function matchScore(
        MarketValue $marketValue,
        ?string $storageVariant,
        ?string $condition
    ): int {
        $score = 0;

        foreach ([
            [$marketValue->storage_variant, $storageVariant],
            [$marketValue->condition, $condition],
        ] as [$value, $requested]) {
            if ($requested !== null && $value === $requested) {
                $score += 2;
            } elseif ($value === null) {
                $score++;
            }
        }

        return $score;
    }
}
