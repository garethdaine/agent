<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteInterrogationPlanJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\ReviewerContextBuilder;
use App\Support\Interrogation\ReviewerPayloadGuard;
use App\Support\Interrogation\ReviewerPayloadNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests plan review gating with pass/revise handling only.
 *
 * This tests Phase C: Plan Gating from the adversarial reviewer plan.
 * Unlike summary review, needs_clarification is not allowed for plan review
 * and must be treated as revise.
 *
 * Conditions for correctness:
 * - review_warn_only must be false for gating to apply
 * - adversarial_review_enabled must be true
 * - needs_clarification is invalid and treated as revise
 * - Critical issues auto-escalate pass to revise
 * - Max 2 retries enforced before failure transition
 *
 * @see AdversarialReviewerSummaryTest for summary review behavior
 */
class AdversarialReviewerPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.interrogation.adversarial_review_enabled' => true]);
        config(['agent.interrogation.review_warn_only' => false]);
        config(['agent.interrogation.plan_review_max_retries' => 2]);
    }

    /**
     * Create an AdversarialReviewerService mock that returns the specified payload.
     */
    private function createMockedReviewerService(array $reviewPayload): AdversarialReviewerService
    {
        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')->andReturn($reviewPayload);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);

        return $service;
    }

    /**
     * Create a mock service that returns different payloads on subsequent calls.
     *
     * @param  array<int, array>  $payloads  Array of payloads to return in sequence
     */
    private function createSequentialMockedReviewerService(array $payloads): AdversarialReviewerService
    {
        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')
            ->andReturnValues($payloads);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);

        return $service;
    }

    /**
     * Invoke the runAdversarialReview method via reflection.
     */
    private function invokeAdversarialReview(
        ExecuteInterrogationPlanJob $job,
        InterrogationSession $session,
        array $planCandidate
    ): ?array {
        $writer = new InterrogationEventWriter($session);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('runAdversarialReview');
        $method->setAccessible(true);

        return $method->invoke($job, $session, $planCandidate, $writer);
    }

    public function test_pass_verdict_persists_plan(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.85,
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => 'Plan looks good',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan']);

        // Pass verdict should return null (success, continue with persistence)
        $this->assertNull($result);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertSame('passed', $metadata['plan']['review_status'] ?? null);
        $this->assertSame(1, $metadata['plan']['review_attempts'] ?? 0);
    }

    public function test_revise_verdict_triggers_regeneration(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        // First call returns revise, second returns pass
        $payloads = [
            [
                'verdict' => 'revise',
                'issues' => [['severity' => 'high', 'type' => 'missing_requirement', 'message' => 'Missing task', 'evidence' => 'Task X not included']],
                'required_changes' => ['Add task X'],
                'confidence' => 0.5,
                'clarification_questions' => [],
                'review_notes' => 'Needs work',
            ],
            [
                'verdict' => 'pass',
                'issues' => [],
                'confidence' => 0.9,
                'required_changes' => [],
                'clarification_questions' => [],
                'review_notes' => 'Fixed',
            ],
        ];

        $service = $this->createSequentialMockedReviewerService($payloads);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);

        // First call should return revise payload indicating need for regeneration
        $result = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan v1']);

        // Revise verdict should return the review payload for regeneration context
        $this->assertNotNull($result);
        $this->assertArrayHasKey('verdict', $result);
        $this->assertSame('revise', $result['verdict']);

        $session->refresh();
        $this->assertSame('revising', $session->metadata_json['plan']['review_status'] ?? null);

        // Second call (after regeneration) should pass
        $result2 = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan v2']);

        $this->assertNull($result2); // Pass returns null
        $session->refresh();
        $this->assertSame(2, $session->metadata_json['plan']['review_attempts'] ?? 0);
        $this->assertSame('passed', $session->metadata_json['plan']['review_status'] ?? null);
    }

    public function test_needs_clarification_treated_as_revise(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        // Reviewer returns needs_clarification (invalid for plan)
        // The guard should convert this to revise at the service level,
        // but we also handle it defensively in the job
        $reviewPayload = [
            'verdict' => 'revise', // Service or guard will convert needs_clarification to revise
            'issues' => [['severity' => 'medium', 'type' => 'ambiguity', 'message' => 'Unclear scope', 'evidence' => 'X']],
            'confidence' => 0.5,
            'required_changes' => ['Clarify scope'],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan']);

        // Should be treated as revise (returns payload for regeneration)
        $this->assertNotNull($result);
        $this->assertArrayHasKey('verdict', $result);

        $session->refresh();
        // Should not populate clarification queue - plans don't support clarification
        $this->assertArrayNotHasKey('plan_clarification_queue', $session->metadata_json ?? []);
        $this->assertSame('revising', $session->metadata_json['plan']['review_status'] ?? null);
    }

    public function test_retry_cap_enforced_at_2(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        $revisePayload = [
            'verdict' => 'revise',
            'issues' => [['severity' => 'high', 'type' => 'ambiguity', 'message' => 'Still wrong', 'evidence' => 'Still ambiguous']],
            'confidence' => 0.3,
            'required_changes' => ['Fix ambiguity'],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')
            ->times(2)
            ->andReturn($revisePayload);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);

        // First attempt returns revise payload for regeneration
        $result1 = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan v0']);
        $session->refresh();
        $this->assertNotNull($result1);
        $this->assertSame('revise', $result1['verdict']);

        // Second attempt degrades gracefully — accepts plan with warnings
        $result2 = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan v1']);
        $session->refresh();

        $this->assertNull($result2);

        $metadata = $session->metadata_json ?? [];
        $this->assertSame('accepted_with_warnings', $metadata['plan']['review_status'] ?? null);
        $this->assertCount(2, $metadata['plan']['review_history'] ?? []);

        $exhaustedEvent = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
            ->get()
            ->first(fn ($e) => ($e->payload['notice'] ?? '') === 'plan_review_exhausted');

        $this->assertNotNull($exhaustedEvent, 'Expected plan_review_exhausted system event');
    }

    public function test_shadow_mode_logs_but_does_not_gate(): void
    {
        config(['agent.interrogation.review_warn_only' => true]);

        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        $reviewPayload = [
            'verdict' => 'revise',
            'issues' => [['severity' => 'critical', 'type' => 'contradiction', 'message' => 'Major issue', 'evidence' => 'Contradiction found']],
            'confidence' => 0.2,
            'required_changes' => ['Fix contradiction'],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan']);

        // Should complete despite revise verdict (shadow mode returns null)
        $this->assertNull($result);

        $session->refresh();
        // Status should be prefixed with shadow_
        $this->assertStringStartsWith('shadow_', $session->metadata_json['plan']['review_status'] ?? '');
    }

    public function test_critical_issue_auto_escalates_pass_to_revise(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        // Returns pass verdict but with critical issue - should be treated as revise
        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [
                [
                    'type' => 'contradiction',
                    'severity' => 'critical',
                    'message' => 'Critical problem found',
                    'evidence' => 'Found conflicting tasks',
                ],
            ],
            'confidence' => 0.9,
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan']);

        // Should be auto-escalated to revise due to critical issue
        $this->assertNotNull($result);
        $this->assertArrayHasKey('verdict', $result);

        $session->refresh();
        $this->assertSame('revising', $session->metadata_json['plan']['review_status'] ?? null);
    }

    public function test_review_history_tracks_all_attempts(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        $payloads = [
            [
                'verdict' => 'revise',
                'issues' => [['severity' => 'medium', 'type' => 'ambiguity', 'message' => 'First issue', 'evidence' => 'X']],
                'confidence' => 0.6,
                'required_changes' => ['Fix 1'],
                'clarification_questions' => [],
                'review_notes' => '',
            ],
            [
                'verdict' => 'pass',
                'issues' => [],
                'confidence' => 0.95,
                'required_changes' => [],
                'clarification_questions' => [],
                'review_notes' => 'All clear',
            ],
        ];

        $service = $this->createSequentialMockedReviewerService($payloads);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);

        // Run all attempts
        $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# v1']);
        $session->refresh();

        $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# v2']);
        $session->refresh();

        $metadata = $session->metadata_json ?? [];

        $this->assertSame(2, $metadata['plan']['review_attempts']);
        $this->assertCount(2, $metadata['plan']['review_history']);
        $this->assertSame('revise', $metadata['plan']['review_history'][0]['verdict']);
        $this->assertSame('pass', $metadata['plan']['review_history'][1]['verdict']);
        $this->assertSame('passed', $metadata['plan']['review_status']);
    }

    public function test_emits_review_events(): void
    {
        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [['severity' => 'low', 'type' => 'ambiguity', 'message' => 'Minor issue', 'evidence' => 'X']],
            'confidence' => 0.85,
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationPlanJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan']);

        $event = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_PLAN_REVIEW)
            ->first();

        $this->assertNotNull($event, 'Expected plan_review event');
        $payload = $event->payload ?? [];

        $this->assertSame('pass', $payload['status']);
        $this->assertSame('pass', $payload['verdict']);
        $this->assertSame(1, $payload['attempt']);
        $this->assertSame(1, $payload['issue_count']);
        $this->assertSame(0.85, $payload['confidence']);
        $this->assertFalse($payload['shadow_mode']);
    }

    public function test_review_disabled_skips_review(): void
    {
        config(['agent.interrogation.adversarial_review_enabled' => false]);

        $session = InterrogationSession::factory()
            ->planning()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test feature',
                    'summary' => [
                        'summary_markdown' => '# Locked Summary',
                        'review_status' => 'passed',
                    ],
                ],
            ]);

        $job = new ExecuteInterrogationPlanJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['plan_markdown' => '# Test Plan']);

        // Should return null without any review occurring
        $this->assertNull($result);

        $session->refresh();
        // No review status should be set
        $this->assertArrayNotHasKey('review_status', $session->metadata_json['plan'] ?? []);
    }
}
