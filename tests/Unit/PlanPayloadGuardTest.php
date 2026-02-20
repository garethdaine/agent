<?php

namespace Tests\Unit;

use App\Support\Interrogation\PlanPayloadGuard;
use Tests\TestCase;

class PlanPayloadGuardTest extends TestCase
{
    public function test_rejects_operational_status_text(): void
    {
        $guard = new PlanPayloadGuard;

        $result = $guard->validate([
            'plan_markdown' => 'Reviewing the locked discovery baseline and existing docs first, then I\'ll rewrite the full implementation plan.',
            'sections' => [],
            'risks' => [],
            'assumptions' => [],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('operational status text', $result['reason']);
    }

    public function test_accepts_structured_plan_payload(): void
    {
        $guard = new PlanPayloadGuard;

        $result = $guard->validate([
            'plan_markdown' => <<<'MD'
## Scope & Contracts
- Lock API tool schemas from docs/tooling/inventory.v1.json.

## Implementation
- Update app/Jobs/ExecuteInterrogationPlanJob.php to validate payload guard.
- Add tests in tests/Unit/ExecuteInterrogationPlanJobTest.php.
MD,
            'sections' => ['Scope & Contracts', 'Implementation'],
            'risks' => ['Schema drift'],
            'assumptions' => ['Summary is approved'],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('ok', $result['reason']);
    }
}
