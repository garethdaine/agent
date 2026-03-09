<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Services\Escalation\WorkflowGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(WorkflowGovernanceService::class);
        $this->assertInstanceOf(WorkflowGovernanceService::class, $service);
    }
}
