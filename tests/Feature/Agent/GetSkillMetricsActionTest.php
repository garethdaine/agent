<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Actions\Agent\GetSkillMetricsAction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetSkillMetricsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $action = new GetSkillMetricsAction;
        $this->assertInstanceOf(GetSkillMetricsAction::class, $action);
    }

    public function test_execute_returns_array(): void
    {
        $action = new GetSkillMetricsAction;

        $result = $action->execute(CarbonImmutable::now()->subDay());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('skill_failure_rate', $result);
        $this->assertArrayHasKey('skill_token_spend', $result);
        $this->assertArrayHasKey('skill_escalations', $result);
    }
}
