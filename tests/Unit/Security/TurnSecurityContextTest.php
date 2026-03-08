<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\Security\TurnSecurityContext;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TurnSecurityContextTest extends TestCase
{
    public function test_fresh_context_is_not_tainted(): void
    {
        $context = new TurnSecurityContext;

        $this->assertFalse($context->isTainted());
    }

    public function test_mark_tainted_sets_taint_state(): void
    {
        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $this->assertTrue($context->isTainted());
        $this->assertEquals('web.fetch', $context->getTaintSource());
    }

    public function test_taint_is_permanent_keeps_first_source(): void
    {
        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');
        $context->markTainted('mcp.call');

        $this->assertTrue($context->isTainted());
        $this->assertEquals('web.fetch', $context->getTaintSource());
    }

    public function test_increment_and_get_tool_call_count(): void
    {
        $context = new TurnSecurityContext;

        $this->assertEquals(0, $context->getToolCallCount());

        $context->incrementToolCall();
        $context->incrementToolCall();

        $this->assertEquals(2, $context->getToolCallCount());
    }

    public function test_increment_and_get_untrusted_result_count(): void
    {
        $context = new TurnSecurityContext;

        $this->assertEquals(0, $context->getUntrustedResultCount());

        $context->incrementUntrustedResult();
        $context->incrementUntrustedResult();
        $context->incrementUntrustedResult();

        $this->assertEquals(3, $context->getUntrustedResultCount());
    }

    public function test_to_array_includes_all_fields(): void
    {
        $context = new TurnSecurityContext;
        $context->markTainted('browser.dom');
        $context->incrementToolCall();
        $context->incrementUntrustedResult();

        $array = $context->toArray();

        $this->assertArrayHasKey('tainted', $array);
        $this->assertArrayHasKey('taint_source', $array);
        $this->assertArrayHasKey('tainted_at', $array);
        $this->assertArrayHasKey('tool_call_count', $array);
        $this->assertArrayHasKey('untrusted_result_count', $array);
        $this->assertTrue($array['tainted']);
        $this->assertEquals('browser.dom', $array['taint_source']);
        $this->assertEquals(1, $array['tool_call_count']);
        $this->assertEquals(1, $array['untrusted_result_count']);
    }

    public function test_get_tainted_at_returns_carbon_when_tainted(): void
    {
        $context = new TurnSecurityContext;
        $context->markTainted('web.fetch');

        $this->assertInstanceOf(CarbonImmutable::class, $context->getTaintedAt());
    }

    public function test_get_tainted_at_returns_null_when_not_tainted(): void
    {
        $context = new TurnSecurityContext;

        $this->assertNull($context->getTaintedAt());
    }
}
