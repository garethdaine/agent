<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Runtime\ToolResult;
use App\DTOs\Security\SecurityToolResult;
use App\Enums\Security\ContentTrustLevel;
use App\Enums\Security\InjectionAction;
use PHPUnit\Framework\TestCase;

class SecurityToolResultTest extends TestCase
{
    public function test_composition_preserves_original_tool_result(): void
    {
        $original = ToolResult::success('test data', 150, ['artifact1']);

        $wrapped = SecurityToolResult::fromToolResult(
            $original,
            ContentTrustLevel::Untrusted,
            'web.fetch',
        );

        $this->assertTrue($original->success);
        $this->assertSame('test data', $original->data);
        $this->assertNull($original->error);
        $this->assertSame(150, $original->durationMs);
        $this->assertSame(['artifact1'], $original->artifacts);
        $this->assertSame($original, $wrapped->original);
    }

    public function test_from_tool_result_produces_correct_defaults(): void
    {
        $original = ToolResult::success('data', 100);

        $wrapped = SecurityToolResult::fromToolResult(
            $original,
            ContentTrustLevel::Contextual,
            'session.context',
        );

        $this->assertSame(0.0, $wrapped->injectionScore);
        $this->assertSame(InjectionAction::Log, $wrapped->injectionAction);
        $this->assertFalse($wrapped->sanitized);
        $this->assertFalse($wrapped->truncated);
        $this->assertSame(0, $wrapped->originalTokenCount);
        $this->assertSame(ContentTrustLevel::Contextual, $wrapped->contentTrustLevel);
        $this->assertSame('session.context', $wrapped->sourceIdentifier);
    }

    public function test_with_injection_result_returns_new_instance(): void
    {
        $original = ToolResult::success('data', 100);
        $wrapped = SecurityToolResult::fromToolResult($original, ContentTrustLevel::Untrusted, 'test');

        $updated = $wrapped->withInjectionResult(0.85, InjectionAction::Block);

        $this->assertNotSame($wrapped, $updated);
        $this->assertSame(0.0, $wrapped->injectionScore);
        $this->assertSame(InjectionAction::Log, $wrapped->injectionAction);
        $this->assertSame(0.85, $updated->injectionScore);
        $this->assertSame(InjectionAction::Block, $updated->injectionAction);
        $this->assertSame($wrapped->contentTrustLevel, $updated->contentTrustLevel);
        $this->assertSame($wrapped->sourceIdentifier, $updated->sourceIdentifier);
    }

    public function test_with_sanitization_returns_new_instance(): void
    {
        $original = ToolResult::success('data', 100);
        $wrapped = SecurityToolResult::fromToolResult($original, ContentTrustLevel::Untrusted, 'test');

        $updated = $wrapped->withSanitization(true, 5000, true);

        $this->assertNotSame($wrapped, $updated);
        $this->assertFalse($wrapped->sanitized);
        $this->assertSame(0, $wrapped->originalTokenCount);
        $this->assertFalse($wrapped->truncated);
        $this->assertTrue($updated->sanitized);
        $this->assertSame(5000, $updated->originalTokenCount);
        $this->assertTrue($updated->truncated);
    }

    public function test_getter_delegation_returns_original_values(): void
    {
        $original = ToolResult::success('test data', 250, ['a', 'b']);
        $wrapped = SecurityToolResult::fromToolResult($original, ContentTrustLevel::Trusted, 'system');

        $this->assertTrue($wrapped->success());
        $this->assertSame('test data', $wrapped->data());
        $this->assertNull($wrapped->error());
        $this->assertSame(250, $wrapped->durationMs());
        $this->assertSame(['a', 'b'], $wrapped->artifacts());
    }

    public function test_wrapping_failure_tool_result_preserves_error_state(): void
    {
        $failure = ToolResult::failure('Something went wrong', 500);
        $wrapped = SecurityToolResult::fromToolResult($failure, ContentTrustLevel::Untrusted, 'mcp.call');

        $this->assertFalse($wrapped->success());
        $this->assertNull($wrapped->data());
        $this->assertSame('Something went wrong', $wrapped->error());
        $this->assertSame(500, $wrapped->durationMs());
        $this->assertSame([], $wrapped->artifacts());
    }
}
