<?php

namespace Tests\Unit\Support\NlOrg;

use App\Support\NlOrg\NlOrgParseResult;
use InvalidArgumentException;
use Tests\TestCase;

class NlOrgParseResultTest extends TestCase
{
    private function makeResult(array $overrides = []): NlOrgParseResult
    {
        return new NlOrgParseResult(
            confidence: $overrides['confidence'] ?? 0.85,
            changeset: $overrides['changeset'] ?? [],
            annotations: $overrides['annotations'] ?? [],
            resolvedReferences: $overrides['resolvedReferences'] ?? [],
            error: $overrides['error'] ?? null,
        );
    }

    public function test_constructor_rejects_confidence_below_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeResult(['confidence' => -0.1]);
    }

    public function test_constructor_rejects_confidence_above_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeResult(['confidence' => 1.1]);
    }

    public function test_constructor_accepts_boundary_values(): void
    {
        $resultZero = $this->makeResult(['confidence' => 0.0]);
        $this->assertSame(0.0, $resultZero->confidence);

        $resultOne = $this->makeResult(['confidence' => 1.0]);
        $this->assertSame(1.0, $resultOne->confidence);
    }

    public function test_to_array_returns_all_fields_correctly_serialized(): void
    {
        $changeset = [
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => ['name' => 'Test Agent'],
                'temp_id' => 'new_1',
            ],
        ];
        $annotations = [
            [
                'message' => 'Ambiguous reference',
                'type' => 'ambiguity',
                'char_offset_start' => 5,
                'char_offset_end' => 15,
                'suggestion' => 'Did you mean Agent Alpha?',
            ],
        ];
        $resolvedReferences = ['Agent Alpha' => 'uuid-123'];

        $result = new NlOrgParseResult(
            confidence: 0.9,
            changeset: $changeset,
            annotations: $annotations,
            resolvedReferences: $resolvedReferences,
            error: null,
        );

        $array = $result->toArray();

        $this->assertSame(0.9, $array['confidence']);
        $this->assertSame($changeset, $array['changeset']);
        $this->assertSame($annotations, $array['annotations']);
        $this->assertSame($resolvedReferences, $array['resolved_references']);
        $this->assertNull($array['error']);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $result = $this->makeResult(['confidence' => 0.75]);

        $this->assertSame($result->toArray(), $result->jsonSerialize());
    }

    public function test_is_high_confidence_returns_true_at_or_above_threshold(): void
    {
        config()->set('agent.nl_org.confidence_threshold', 0.7);

        $result = $this->makeResult(['confidence' => 0.7]);
        $this->assertTrue($result->isHighConfidence());

        $result = $this->makeResult(['confidence' => 0.95]);
        $this->assertTrue($result->isHighConfidence());
    }

    public function test_is_high_confidence_returns_false_below_threshold(): void
    {
        config()->set('agent.nl_org.confidence_threshold', 0.7);

        $result = $this->makeResult(['confidence' => 0.69]);
        $this->assertFalse($result->isHighConfidence());
    }

    public function test_has_error_returns_true_when_error_is_non_null(): void
    {
        $result = $this->makeResult(['error' => 'Context window exceeded']);
        $this->assertTrue($result->hasError());
    }

    public function test_has_error_returns_false_when_error_is_null(): void
    {
        $result = $this->makeResult(['error' => null]);
        $this->assertFalse($result->hasError());
    }

    public function test_get_changeset_by_entity_type_filters_correctly(): void
    {
        $changeset = [
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => ['name' => 'Agent A'],
                'temp_id' => null,
            ],
            [
                'entity_type' => NlOrgParseResult::TYPE_REPORTING_EDGE,
                'operation' => NlOrgParseResult::OP_ADD,
                'payload' => ['parent_id' => '1', 'child_id' => '2'],
                'temp_id' => null,
            ],
            [
                'entity_type' => NlOrgParseResult::TYPE_AGENT_PROFILE,
                'operation' => NlOrgParseResult::OP_UPDATE,
                'payload' => ['name' => 'Agent B'],
                'temp_id' => null,
            ],
        ];

        $result = $this->makeResult(['changeset' => $changeset]);

        $filtered = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_AGENT_PROFILE);
        $this->assertCount(2, $filtered);
        $this->assertSame('Agent A', $filtered[0]['payload']['name']);
        $this->assertSame('Agent B', $filtered[1]['payload']['name']);

        $edges = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_REPORTING_EDGE);
        $this->assertCount(1, $edges);

        $empty = $result->getChangesetByEntityType(NlOrgParseResult::TYPE_COST_LEDGER);
        $this->assertEmpty($empty);
    }

    public function test_entity_type_constants_exist(): void
    {
        $this->assertSame('org_agent_profile', NlOrgParseResult::TYPE_AGENT_PROFILE);
        $this->assertSame('org_reporting_edge', NlOrgParseResult::TYPE_REPORTING_EDGE);
        $this->assertSame('org_ritual_template', NlOrgParseResult::TYPE_RITUAL_TEMPLATE);
        $this->assertSame('org_council_template', NlOrgParseResult::TYPE_COUNCIL_TEMPLATE);
        $this->assertSame('org_cost_ledger', NlOrgParseResult::TYPE_COST_LEDGER);
    }

    public function test_operation_constants_exist(): void
    {
        $this->assertSame('add', NlOrgParseResult::OP_ADD);
        $this->assertSame('update', NlOrgParseResult::OP_UPDATE);
        $this->assertSame('remove', NlOrgParseResult::OP_REMOVE);
    }
}
