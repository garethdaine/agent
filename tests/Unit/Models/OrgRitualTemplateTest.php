<?php

namespace Tests\Unit\Models;

use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgRitualTemplateTest extends TestCase
{
    use RefreshDatabase;
    public function test_scheduled_scope_excludes_paused_and_archived(): void
    {
        $user = User::factory()->create();
        OrgRitualTemplate::factory()->for($user)->create(['is_paused' => false]);
        OrgRitualTemplate::factory()->for($user)->create(['is_paused' => true]);
        OrgRitualTemplate::factory()->for($user)->create(['archived_at' => now()]);

        $scheduled = OrgRitualTemplate::scheduled()->count();

        $this->assertEquals(1, $scheduled);
    }

    public function test_ritual_run_states_are_valid(): void
    {
        $validStates = [
            'draft', 'scheduled', 'queued', 'running', 'waiting_approval',
            'reviewing', 'succeeded', 'failed', 'cancelled', 'partial'
        ];

        foreach ($validStates as $state) {
            $run = OrgRitualRun::factory()->make(['state' => $state]);
            $this->assertEquals($state, $run->state);
        }
    }
}
