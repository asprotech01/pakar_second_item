<?php

namespace Tests\Unit;

use App\Services\ExpertSystem\CertaintyFactorEngine;
use App\Services\ExpertSystem\ForwardChainingEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpertSystemEngineTest extends TestCase
{
    #[Test]
    public function forward_chaining_repeats_until_derived_facts_unlock_rules_and_unknown_is_not_absent(): void
    {
        $engine = new ForwardChainingEngine;
        $result = $engine->evaluate(
            ['display_issue' => ['value' => 'issue', 'certainty' => 0.8]],
            [
                [
                    'code' => 'R2',
                    'conclusion' => 'device_needs_review',
                    'conditions' => [['fact' => 'repair_needed', 'operator' => 'PRESENT']],
                ],
                [
                    'code' => 'R1',
                    'conclusion' => 'repair_needed',
                    'conditions' => [['fact' => 'display_issue', 'operator' => 'PRESENT']],
                ],
                [
                    'code' => 'UNKNOWN_IS_NORMAL',
                    'conclusion' => 'device_viable',
                    'conditions' => [['fact' => 'battery_issue', 'operator' => 'ABSENT']],
                ],
            ]
        );

        $this->assertSame(['R1', 'R2'], array_column($result['fired_rules'], 'code'));
        $this->assertSame(['repair_needed', 'device_needs_review'], $result['inferred_facts']);
        $this->assertArrayNotHasKey('device_viable', $result['known_facts']);
    }

    #[Test]
    public function certainty_factor_engine_combines_supporting_and_conflicting_evidence(): void
    {
        $engine = new CertaintyFactorEngine;

        $this->assertEqualsWithDelta(0.80, $engine->combine([0.60, 0.50]), 0.0001);
        $this->assertEqualsWithDelta(-0.70, $engine->combine([-0.40, -0.50]), 0.0001);
        $this->assertEqualsWithDelta(2 / 3, $engine->combine([0.80, -0.40]), 0.0001);
    }
}
