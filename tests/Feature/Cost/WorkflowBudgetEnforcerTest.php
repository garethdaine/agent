<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Services\Cost\WorkflowBudgetEnforcer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowBudgetEnforcerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(WorkflowBudgetEnforcer::class);
        $this->assertInstanceOf(WorkflowBudgetEnforcer::class, $service);
    }
}
