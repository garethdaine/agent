<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Delegation;

use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Support\Delegation\DelegateeMetricsRecomputer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DelegateeMetricsRecomputerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegateeProfile $profile;

    private DelegationGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $this->graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_computes_24h_success_rate(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        // Create 3 successful attempts in last 24h
        for ($i = 0; $i < 3; $i++) {
            DelegationAttempt::factory()->succeeded()->create([
                'delegation_task_id' => $task->id,
                'delegatee_profile_id' => $this->profile->id,
                'finished_at' => now()->subHours(rand(1, 20)),
            ]);
        }

        // Create 2 failed attempts in last 24h
        for ($i = 0; $i < 2; $i++) {
            DelegationAttempt::factory()->failed()->create([
                'delegation_task_id' => $task->id,
                'delegatee_profile_id' => $this->profile->id,
                'finished_at' => now()->subHours(rand(1, 20)),
            ]);
        }

        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recompute($this->profile);

        $metric = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();

        $this->assertNotNull($metric);
        $this->assertNotNull($metric->window_24h_json);
        $this->assertEquals(5, $metric->window_24h_json['total_attempts']);
        $this->assertEquals(3, $metric->window_24h_json['successful_attempts']);
        $this->assertEquals(0.6, $metric->window_24h_json['success_rate']); // 3/5 = 0.6
    }

    public function test_computes_7d_success_rate(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        // Create 10 successful attempts in last 7 days
        for ($i = 0; $i < 10; $i++) {
            DelegationAttempt::factory()->succeeded()->create([
                'delegation_task_id' => $task->id,
                'delegatee_profile_id' => $this->profile->id,
                'finished_at' => now()->subDays(rand(0, 6))->subHours(rand(1, 20)),
            ]);
        }

        // Create 5 failed attempts in last 7 days
        for ($i = 0; $i < 5; $i++) {
            DelegationAttempt::factory()->failed()->create([
                'delegation_task_id' => $task->id,
                'delegatee_profile_id' => $this->profile->id,
                'finished_at' => now()->subDays(rand(0, 6))->subHours(rand(1, 20)),
            ]);
        }

        // Create attempts outside the 7-day window (should be excluded)
        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'finished_at' => now()->subDays(10),
        ]);

        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recompute($this->profile);

        $metric = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();

        $this->assertNotNull($metric);
        $this->assertNotNull($metric->window_7d_json);
        $this->assertEquals(15, $metric->window_7d_json['total_attempts']);
        $this->assertEquals(10, $metric->window_7d_json['successful_attempts']);
        $this->assertEqualsWithDelta(0.6667, $metric->window_7d_json['success_rate'], 0.001); // 10/15
    }

    public function test_throttles_event_triggered_recompute(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $recomputer = new DelegateeMetricsRecomputer;

        // First call should work
        $result1 = $recomputer->recomputeIfNotThrottled($this->profile);
        $this->assertTrue($result1);

        // Second call within throttle window should be blocked
        $result2 = $recomputer->recomputeIfNotThrottled($this->profile);
        $this->assertFalse($result2);

        // Clear cache and try again
        Cache::forget("delegatee_metrics_recompute:{$this->profile->id}");
        $result3 = $recomputer->recomputeIfNotThrottled($this->profile);
        $this->assertTrue($result3);
    }

    public function test_upserts_metric_record(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        // First recompute - creates record
        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recompute($this->profile);

        $this->assertDatabaseCount('delegatee_metrics', 1);

        $metric = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();
        $this->assertEquals(1, $metric->window_24h_json['total_attempts']);

        // Second recompute - updates existing record
        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        // Clear throttle cache
        Cache::forget("delegatee_metrics_recompute:{$this->profile->id}");
        $recomputer->recompute($this->profile);

        $this->assertDatabaseCount('delegatee_metrics', 1);

        $metric->refresh();
        $this->assertEquals(2, $metric->window_24h_json['total_attempts']);
    }

    public function test_computes_average_duration(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        // Create attempts with known durations
        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'duration_ms' => 1000,
            'finished_at' => now()->subHours(1),
        ]);

        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
            'duration_ms' => 3000,
            'finished_at' => now()->subHours(2),
        ]);

        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recompute($this->profile);

        $metric = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();

        $this->assertEquals(2000, $metric->window_24h_json['avg_duration_ms']); // (1000 + 3000) / 2
    }

    public function test_handles_no_attempts(): void
    {
        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recompute($this->profile);

        $metric = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();

        $this->assertNotNull($metric);
        $this->assertEquals(0, $metric->window_24h_json['total_attempts']);
        $this->assertEquals(0, $metric->window_24h_json['successful_attempts']);
        $this->assertEquals(0.0, $metric->window_24h_json['success_rate']);
        $this->assertEquals(0, $metric->window_24h_json['avg_duration_ms']);
    }

    public function test_recompute_all_profiles(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        // Create second profile
        $profile2 = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        DelegationAttempt::factory()->succeeded()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $this->profile->id,
        ]);

        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $task->id,
            'delegatee_profile_id' => $profile2->id,
        ]);

        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recomputeAll();

        $this->assertDatabaseCount('delegatee_metrics', 2);

        $metric1 = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();
        $metric2 = DelegateeMetric::where('delegatee_profile_id', $profile2->id)->first();

        $this->assertEquals(1.0, $metric1->window_24h_json['success_rate']);
        $this->assertEquals(0.0, $metric2->window_24h_json['success_rate']);
    }

    public function test_updates_last_recomputed_at(): void
    {
        $recomputer = new DelegateeMetricsRecomputer;
        $recomputer->recompute($this->profile);

        $metric = DelegateeMetric::where('delegatee_profile_id', $this->profile->id)->first();

        $this->assertNotNull($metric->last_recomputed_at);
        $this->assertTrue($metric->last_recomputed_at->isToday());
    }
}
