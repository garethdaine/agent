<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\User;
use App\Support\Documentation\DocsTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DocsDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_docs_diagnostics_endpoint(): void
    {
        $this->getJson('/agent/api/v1/docs/diagnostics')->assertUnauthorized();
    }

    public function test_non_operator_user_is_forbidden_from_docs_diagnostics_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/diagnostics')
            ->assertForbidden();
    }

    public function test_operator_can_access_docs_diagnostics_with_counters_and_recent_failures(): void
    {
        $user = User::factory()->create();
        config()->set('agent.roles.admin_user_ids', [$user->id]);

        $telemetry = app(DocsTelemetryService::class);

        $telemetry->recordTooltipMiss('unknown.ui.key', 'missing_key', ['source' => 'api']);
        $telemetry->recordSearchUnavailable(
            query: 'agent',
            routeName: 'agent.jobs.index',
            throwable: new RuntimeException('typesense down'),
            context: ['source' => 'api']
        );
        $telemetry->recordSyncOutcome(
            mode: 'deploy',
            source: 'repo',
            success: false,
            summary: [
                'entries_count' => 0,
                'fragments_count' => 0,
                'links_count' => 0,
            ],
            errorCode: 'DOCS_SYNC_FAILED',
            errorMessage: 'simulated failure'
        );

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/diagnostics')
            ->assertOk()
            ->assertJsonPath('data.counters.tooltip_missing_key_total', 1)
            ->assertJsonPath('data.counters.docs_search_unavailable_total', 1)
            ->assertJsonPath('data.counters.docs_sync_failure_total', 1)
            ->assertJsonPath('data.generated_at', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.recent_failures.0.event', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('agent_audit_logs', [
            'actor_type' => 'system',
            'action' => 'docs.telemetry.tooltip_missing_key',
            'target_type' => 'documentation_telemetry',
            'target_id' => 'unknown.ui.key',
            'outcome' => 'success',
        ]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'actor_type' => 'system',
            'action' => 'docs.telemetry.search_unavailable',
            'target_type' => 'documentation_telemetry',
            'outcome' => 'success',
        ]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'actor_type' => 'system',
            'action' => 'docs.telemetry.sync_outcome',
            'target_type' => 'documentation_telemetry',
            'target_id' => 'deploy:repo',
            'outcome' => 'success',
        ]);
    }
}
