<?php

namespace Tests\Unit\Models;

use App\Models\OrgAgentProfile;
use Tests\TestCase;

class OrgAgentProfileTest extends TestCase
{
    public function test_soul_json_is_fillable(): void
    {
        $profile = new OrgAgentProfile;

        $this->assertContains('soul_json', $profile->getFillable());
    }

    public function test_soul_json_is_cast_to_array(): void
    {
        $profile = new OrgAgentProfile;

        $this->assertArrayHasKey('soul_json', $profile->getCasts());
        $this->assertEquals('array', $profile->getCasts()['soul_json']);
    }

    public function test_nl_org_max_input_length_config(): void
    {
        $this->assertEquals(2000, config('agent.nl_org.max_input_length'));
    }

    public function test_nl_org_confidence_threshold_config(): void
    {
        $this->assertEquals(0.7, config('agent.nl_org.confidence_threshold'));
    }

    public function test_nl_org_max_chat_history_turns_config(): void
    {
        $this->assertEquals(10, config('agent.nl_org.max_chat_history_turns'));
    }
}
