<?php

namespace Tests\Unit\Enums\Messenger;

use App\Enums\Messenger\ApprovalMode;
use PHPUnit\Framework\TestCase;

class ApprovalModeTest extends TestCase
{
    public function test_has_required_cases(): void
    {
        $values = array_map(fn ($c) => $c->value, ApprovalMode::cases());

        $this->assertContains('autonomous', $values);
        $this->assertContains('supervised', $values);
        $this->assertContains('restricted', $values);
        $this->assertCount(3, $values);
    }

    public function test_try_from_returns_correct_case(): void
    {
        $this->assertSame(ApprovalMode::Autonomous, ApprovalMode::tryFrom('autonomous'));
        $this->assertSame(ApprovalMode::Supervised, ApprovalMode::tryFrom('supervised'));
        $this->assertSame(ApprovalMode::Restricted, ApprovalMode::tryFrom('restricted'));
    }

    public function test_try_from_returns_null_for_invalid(): void
    {
        $this->assertNull(ApprovalMode::tryFrom('invalid'));
        $this->assertNull(ApprovalMode::tryFrom(''));
    }

    public function test_label_returns_human_readable_name(): void
    {
        $this->assertSame('Autonomous', ApprovalMode::Autonomous->label());
        $this->assertSame('Supervised', ApprovalMode::Supervised->label());
        $this->assertSame('Restricted', ApprovalMode::Restricted->label());
    }

    public function test_description_returns_non_empty_string(): void
    {
        foreach (ApprovalMode::cases() as $mode) {
            $this->assertNotEmpty($mode->description());
        }
    }
}
