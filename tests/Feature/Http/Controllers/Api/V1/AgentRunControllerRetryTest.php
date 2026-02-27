<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunControllerRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_retry_endpoint_returns_new_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->for($user)->create(['targeted_retry_enabled' => true]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'metadata_json' => [
                'reasoning_summary' => [
                    'steps' => [
                        'situation' => ['completed' => true, 'content' => 'State'],
                        'task' => ['completed' => true, 'content' => 'Goal'],
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/agent/api/v1/runs/{$run->id}/retry");

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'status']]);
    }

    public function test_manual_retry_accepts_custom_prompt(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->for($user)->create(['targeted_retry_enabled' => true]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'metadata_json' => ['reasoning_summary' => ['steps' => ['task' => ['completed' => true]]]]
        ]);

        $customPrompt = 'Custom retry instructions here';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/agent/api/v1/runs/{$run->id}/retry", [
                'retry_prompt' => $customPrompt
            ]);

        $response->assertStatus(201);

        $newRun = AgentJobRun::find($response->json('data.id'));
        $this->assertEquals($customPrompt, $newRun->metadata_json['retry_prompt']);
    }

    public function test_manual_retry_rejects_non_failed_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->for($user)->create();
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/agent/api/v1/runs/{$run->id}/retry");

        $response->assertStatus(422);
    }
}
