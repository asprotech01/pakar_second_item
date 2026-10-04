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
    public function forward_chaining_supports_condition_groups_and_numeric_operators(): void
    {
        $result = (new ForwardChainingEngine)->evaluate(
            [
                'battery_health' => ['value' => 55],
                'screen_issue' => ['value' => 'issue'],
            ],
            [
                [
                    'code' => 'BATTERY_OR_SCREEN',
                    'conclusion' => 'needs_review',
                    'conditions' => [
                        ['fact' => 'battery_health', 'operator' => 'GREATER_THAN_OR_EQUAL', 'expected_value' => '80', 'group_number' => 1],
                        ['fact' => 'battery_health', 'operator' => 'LESS_THAN', 'expected_value' => '90', 'group_number' => 1],
                        ['fact' => 'screen_issue', 'operator' => 'EQUALS', 'expected_value' => 'issue', 'group_number' => 2],
                    ],
                ],
                [
                    'code' => 'WITHIN_GROUP_OR',
                    'conclusion' => 'screen_review',
                    'conditions' => [
                        ['fact' => 'battery_health', 'operator' => 'GREATER_THAN', 'expected_value' => '80', 'group_number' => 1],
                        ['fact' => 'screen_issue', 'operator' => 'EQUALS', 'expected_value' => 'issue', 'group_number' => 1, 'logical_operator' => 'OR'],
                    ],
                ],
                [
                    'code' => 'BATTERY_RANGE',
                    'conclusion' => 'battery_review',
                    'conditions' => [
                        ['fact' => 'battery_health', 'operator' => 'GREATER_THAN_OR_EQUAL', 'expected_value' => '50', 'group_number' => 1],
                        ['fact' => 'battery_health', 'operator' => 'LESS_THAN', 'expected_value' => '60', 'group_number' => 1],
                    ],
                ],
                [
                    'code' => 'AND_GUARD',
                    'conclusion' => 'unexpected_review',
                    'conditions' => [
                        ['fact' => 'battery_health', 'operator' => 'GREATER_THAN_OR_EQUAL', 'expected_value' => '80', 'group_number' => 1],
                        ['fact' => 'battery_health', 'operator' => 'LESS_THAN', 'expected_value' => '60', 'group_number' => 1],
                    ],
                ],
            ]
        );

        $this->assertSame(
            ['BATTERY_OR_SCREEN', 'WITHIN_GROUP_OR', 'BATTERY_RANGE'],
            array_column($result['fired_rules'], 'code')
        );
    }

    #[Test]
    public function certainty_factor_engine_combines_supporting_and_conflicting_evidence(): void
    {
        $engine = new CertaintyFactorEngine;

        $this->assertEqualsWithDelta(0.80, $engine->combine([0.60, 0.50]), 0.0001);
        $this->assertEqualsWithDelta(-0.70, $engine->combine([-0.40, -0.50]), 0.0001);
        $this->assertEqualsWithDelta(2 / 3, $engine->combine([0.80, -0.40]), 0.0001);
    }

    #[Test]
    public function certainty_factor_uses_and_or_logic_for_matched_conditions(): void
    {
        $result = (new CertaintyFactorEngine)->evaluate(
            [
                'strong_evidence' => ['certainty' => 0.8],
                'weak_evidence' => ['certainty' => 0.4],
            ],
            [
                [
                    'code' => 'OR_RULE',
                    'conclusion' => 'or_conclusion',
                    'certainty_factor' => 0.5,
                    'conditions' => [],
                    'matched_condition_groups' => [[
                        ['fact' => 'strong_evidence', 'logical_operator' => 'AND'],
                        ['fact' => 'weak_evidence', 'logical_operator' => 'OR'],
                    ]],
                ],
                [
                    'code' => 'AND_RULE',
                    'conclusion' => 'and_conclusion',
                    'certainty_factor' => 0.5,
                    'conditions' => [],
                    'matched_condition_groups' => [[
                        ['fact' => 'strong_evidence', 'logical_operator' => 'AND'],
                        ['fact' => 'weak_evidence', 'logical_operator' => 'AND'],
                    ]],
                ],
            ]
        );

        $this->assertSame([0.4, 0.2], array_column($result['rule_contributions'], 'certainty_factor'));
    }

    #[Test]
    public function missing_requirements_mark_rules_as_not_evaluable_instead_of_false(): void
    {
        $result = (new ForwardChainingEngine)->evaluate(
            ['screen_issue' => ['value' => 'issue']],
            [[
                'code' => 'BATTERY_HEALTH_CHECK',
                'conclusion' => 'battery_risk',
                'conditions' => [[
                    'fact' => 'battery_health',
                    'operator' => 'LESS_THAN',
                    'expected_value' => '70',
                    'group_number' => 1,
                ]],
            ]]
        );

        $this->assertSame('BATTERY_HEALTH_CHECK', $result['not_evaluable_rules'][0]['code']);
        $this->assertSame('NOT_EVALUABLE', $result['not_evaluable_rules'][0]['status']);
        $this->assertSame(['battery_health'], $result['not_evaluable_rules'][0]['missing_facts']);
    }
}
