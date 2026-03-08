<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TaskCategory;
use PHPUnit\Framework\TestCase;

class TaskCategoryTest extends TestCase
{
    public function test_has_expected_cases(): void
    {
        $cases = TaskCategory::cases();

        $this->assertCount(6, $cases);

        $values = array_column($cases, 'value');
        $this->assertContains('feature', $values);
        $this->assertContains('bugfix', $values);
        $this->assertContains('refactor', $values);
        $this->assertContains('docs', $values);
        $this->assertContains('test', $values);
        $this->assertContains('custom', $values);
    }

    public function test_from_string_returns_matching_case(): void
    {
        $this->assertSame(TaskCategory::FEATURE, TaskCategory::fromString('feature'));
        $this->assertSame(TaskCategory::BUGFIX, TaskCategory::fromString('bugfix'));
        $this->assertSame(TaskCategory::REFACTOR, TaskCategory::fromString('refactor'));
        $this->assertSame(TaskCategory::DOCS, TaskCategory::fromString('docs'));
        $this->assertSame(TaskCategory::TEST, TaskCategory::fromString('test'));
        $this->assertSame(TaskCategory::CUSTOM, TaskCategory::fromString('custom'));
    }

    public function test_from_string_is_case_insensitive(): void
    {
        $this->assertSame(TaskCategory::FEATURE, TaskCategory::fromString('FEATURE'));
        $this->assertSame(TaskCategory::FEATURE, TaskCategory::fromString('Feature'));
        $this->assertSame(TaskCategory::BUGFIX, TaskCategory::fromString('BugFix'));
        $this->assertSame(TaskCategory::REFACTOR, TaskCategory::fromString('REFACTOR'));
    }

    public function test_from_string_returns_custom_for_unknown_values(): void
    {
        $this->assertSame(TaskCategory::CUSTOM, TaskCategory::fromString('unknown'));
        $this->assertSame(TaskCategory::CUSTOM, TaskCategory::fromString(''));
        $this->assertSame(TaskCategory::CUSTOM, TaskCategory::fromString('random_value'));
        $this->assertSame(TaskCategory::CUSTOM, TaskCategory::fromString('enhancement'));
    }
}
