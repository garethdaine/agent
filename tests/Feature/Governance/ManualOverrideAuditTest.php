<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualOverrideAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_resume_persists_required_audit_fields(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        config()->set('agent.roles.admin_user_ids', [$admin->id]);

        $job = AgentJob::factory()->create([
            'user_id' => $owner->id,
            'workflow_key' => 'eng.release-readiness.v1',
            'governance_paused_at' => now('UTC')->subMinute(),
            'governance_pause_reason' => 'Hard gate pause',
            'governance_paused_by' => 'system:on-call',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/resume', [
            'reason' => 'Admin manual override after review',
            'run_id' => 'run-777',
            'force' => true,
        ])->assertOk();

        $audit = DB::table('manual_override_audits')
            ->where('workflow_key', $job->workflow_key)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((string) $admin->id, (string) $audit->actor_id);
        $this->assertNotNull($audit->timestamp);
        $this->assertSame('run-777', $audit->run_id);
        $this->assertSame('paused', $audit->previous_state);
        $this->assertSame('active', $audit->new_state);
        $this->assertSame('Admin manual override after review', $audit->reason);
    }

    public function test_unauthorized_resume_attempt_is_denied_and_audited(): void
    {
        $owner = User::factory()->create();
        $unauthorized = User::factory()->create();

        $job = AgentJob::factory()->create([
            'user_id' => $owner->id,
            'workflow_key' => 'eng.dependency-update-triage.v1',
            'governance_paused_at' => now('UTC')->subMinute(),
            'governance_pause_reason' => 'Incident open',
            'governance_paused_by' => 'system:on-call',
        ]);

        Sanctum::actingAs($unauthorized);

        $this->postJson('/agent/api/v1/workflows/'.$job->workflow_key.'/resume', [
            'reason' => 'Unauthorized override attempt',
            'run_id' => 'run-901',
            'force' => true,
        ])->assertStatus(403);

        $audit = DB::table('manual_override_audits')
            ->where('workflow_key', $job->workflow_key)
            ->where('run_id', 'run-901')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertFalse((bool) $audit->authorized);
        $this->assertSame((string) $unauthorized->id, (string) $audit->actor_id);
        $this->assertNotNull($audit->timestamp);
        $this->assertSame('paused', $audit->previous_state);
        $this->assertSame('active', $audit->new_state);
        $this->assertSame('Unauthorized override attempt', $audit->reason);
    }
}
