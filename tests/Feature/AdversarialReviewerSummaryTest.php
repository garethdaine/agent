<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteInterrogationSummaryJob;
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
 * Tests full summary review gating with pass/revise/needs_clarification verdicts.
 *
 * This tests Phase B: Summary Gating from the adversarial reviewer plan.
 * Unlike shadow mode (tested in AdversarialReviewerShadowModeTest), these tests
 * verify that verdicts actually gate artifact persistence:
 * - pass: persists summary
 * - revise: triggers regeneration loop with retry limits
 * - needs_clarification: populates open question queue
 *
 * Conditions for correctness:
 * - review_warn_only must be false for gating to apply
 * - adversarial_review_enabled must be true
 * - Critical issues auto-escalate pass to revise
 * - Max 3 retries enforced before failure transition
 *
 * @see AdversarialReviewerShadowModeTest for shadow mode behavior
 */
class AdversarialReviewerSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.interrogation.adversarial_review_enabled' => true]);
        config(['agent.interrogation.review_warn_only' => false]);
        config(['agent.interrogation.summary_review_max_retries' => 3]);
        config(['agent.interrogation.review_low_confidence_threshold' => 0.6]);
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

        // Set up sequential returns using andReturnValues
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
        ExecuteInterrogationSummaryJob $job,
        InterrogationSession $session,
        array $summaryCandidate
    ): ?array {
        $writer = new InterrogationEventWriter($session);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('runAdversarialReview');
        $method->setAccessible(true);

        return $method->invoke($job, $session, $summaryCandidate, $writer);
    }

    public function test_pass_verdict_persists_summary(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => ['brief' => 'Test brief'],
            ]);

        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.9,
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => 'Looks good',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        // Pass verdict should return null (success, continue with persistence)
        $this->assertNull($result);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertSame('passed', $metadata['summary']['review_status'] ?? null);
        $this->assertSame(1, $metadata['summary']['review_attempts'] ?? 0);
    }

    public function test_revise_verdict_triggers_regeneration(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        // First call returns revise, second returns pass
        $payloads = [
            [
                'verdict' => 'revise',
                'issues' => [['severity' => 'high', 'type' => 'missing_requirement', 'message' => 'Fix this', 'evidence' => 'Missing X']],
                'required_changes' => ['Add X'],
                'confidence' => 0.5,
                'clarification_questions' => [],
                'review_notes' => 'Needs work',
            ],
            [
                'verdict' => 'pass',
                'issues' => [],
                'confidence' => 0.85,
                'required_changes' => [],
                'clarification_questions' => [],
                'review_notes' => 'Fixed',
            ],
        ];

        $service = $this->createSequentialMockedReviewerService($payloads);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);

        // First call should return revise payload indicating need for regeneration
        $result = $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary v1']);

        // Revise verdict should return the review payload for regeneration context
        $this->assertNotNull($result);
        $this->assertArrayHasKey('verdict', $result);
        $this->assertSame('revise', $result['verdict']);

        $session->refresh();
        $this->assertSame('revising', $session->metadata_json['summary']['review_status'] ?? null);

        // Second call (after regeneration) should pass
        $result2 = $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary v2']);

        $this->assertNull($result2); // Pass returns null
        $session->refresh();
        $this->assertSame(2, $session->metadata_json['summary']['review_attempts'] ?? 0);
        $this->assertSame('passed', $session->metadata_json['summary']['review_status'] ?? null);
    }

    public function test_critical_issue_auto_escalates_pass_to_revise(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        // Returns pass verdict but with critical issue - should be treated as revise
        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [
                [
                    'type' => 'contradiction',
                    'severity' => 'critical',
                    'message' => 'Critical problem found',
                    'evidence' => 'Found conflicting requirements',
                ],
            ],
            'confidence' => 0.9,
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        // Should be auto-escalated to revise due to critical issue
        $this->assertNotNull($result);
        $this->assertArrayHasKey('verdict', $result);

        $session->refresh();
        $this->assertSame('revising', $session->metadata_json['summary']['review_status'] ?? null);
    }

    public function test_needs_clarification_populates_queue(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => ['brief' => 'Test brief'],
            ]);

        $reviewPayload = [
            'verdict' => 'needs_clarification',
            'issues' => [],
            'confidence' => 0.4,
            'required_changes' => [],
            'clarification_questions' => ['What is the auth flow?', 'Which database?'],
            'review_notes' => 'Need more info',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        // Should return halt signal
        $this->assertNotNull($result);
        $this->assertArrayHasKey('halt', $result);
        $this->assertTrue($result['halt']);
        $this->assertSame('clarification_needed', $result['reason']);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        // Questions should be in queue at front (high priority)
        $queue = $metadata['summary_open_question_queue'] ?? [];
        $this->assertNotEmpty($queue);
        $this->assertTrue($queue['active'] ?? false);
        $this->assertCount(2, $queue['questions'] ?? []);
        $this->assertSame('What is the auth flow?', $queue['questions'][0]['text'] ?? '');
        $this->assertSame('reviewer', $queue['questions'][0]['source'] ?? '');

        $this->assertSame('clarification_needed', $metadata['summary']['review_status'] ?? null);
    }

    public function test_retry_cap_enforced(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        $revisePayload = [
            'verdict' => 'revise',
            'issues' => [['severity' => 'high', 'type' => 'ambiguity', 'message' => 'Keeps failing', 'evidence' => 'Still ambiguous']],
            'confidence' => 0.3,
            'required_changes' => ['Fix ambiguity'],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        // Create service that always returns revise
        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')
            ->times(3)
            ->andReturn($revisePayload);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);

        // Run 3 attempts
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->invokeAdversarialReview($job, $session, ['summary_markdown' => "# Test Summary v{$i}"]);
                $session->refresh();
            } catch (\RuntimeException $e) {
                // Expected on 3rd attempt
                $this->assertStringContainsString('exhausted retries', $e->getMessage());
            }
        }

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertSame('failed', $metadata['summary']['review_status'] ?? null);
        $this->assertCount(3, $metadata['summary']['review_history'] ?? []);
    }

    public function test_low_confidence_pass_logs_warning(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.4, // Below threshold of 0.6
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => 'Low confidence but passing',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $result = $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        // Should still pass (return null)
        $this->assertNull($result);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        // Warning should be set
        $this->assertTrue($metadata['summary']['low_confidence_warning'] ?? false);
        // Should still pass, not block
        $this->assertSame('passed', $metadata['summary']['review_status'] ?? null);
    }

    public function test_review_history_tracks_all_attempts(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

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
                'verdict' => 'revise',
                'issues' => [['severity' => 'low', 'type' => 'weak_acceptance_criteria', 'message' => 'Second issue', 'evidence' => 'Y']],
                'confidence' => 0.7,
                'required_changes' => ['Fix 2'],
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

        $job = new ExecuteInterrogationSummaryJob($session->id);

        // Run all attempts
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# v1']);
        $session->refresh();

        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# v2']);
        $session->refresh();

        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# v3']);
        $session->refresh();

        $metadata = $session->metadata_json ?? [];

        $this->assertSame(3, $metadata['summary']['review_attempts']);
        $this->assertCount(3, $metadata['summary']['review_history']);
        $this->assertSame('revise', $metadata['summary']['review_history'][0]['verdict']);
        $this->assertSame('revise', $metadata['summary']['review_history'][1]['verdict']);
        $this->assertSame('pass', $metadata['summary']['review_history'][2]['verdict']);
        $this->assertSame('passed', $metadata['summary']['review_status']);
    }

    public function test_clarification_questions_inserted_at_front_of_queue(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => [
                    'brief' => 'Test brief',
                    'summary_open_question_queue' => [
                        'questions' => [['text' => 'Existing question?', 'source' => 'user']],
                        'active' => false,
                    ],
                ],
            ]);

        $reviewPayload = [
            'verdict' => 'needs_clarification',
            'issues' => [],
            'confidence' => 0.3,
            'required_changes' => [],
            'clarification_questions' => ['New Q1?', 'New Q2?'],
            'review_notes' => '',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        $session->refresh();
        $queue = $session->metadata_json['summary_open_question_queue'] ?? [];

        // New questions should be at front (high priority), existing at back
        $this->assertCount(3, $queue['questions'] ?? []);
        $this->assertSame('New Q1?', $queue['questions'][0]['text'] ?? '');
        $this->assertSame('reviewer', $queue['questions'][0]['source'] ?? '');
        $this->assertSame('New Q2?', $queue['questions'][1]['text'] ?? '');
        $this->assertSame('Existing question?', $queue['questions'][2]['text'] ?? '');
        $this->assertTrue($queue['active']);
    }

    public function test_emits_review_events(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

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

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        $event = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_SUMMARY_REVIEW)
            ->first();

        $this->assertNotNull($event, 'Expected summary_review event');
        $payload = $event->payload ?? [];

        $this->assertSame('pass', $payload['status']);
        $this->assertSame('pass', $payload['verdict']);
        $this->assertSame(1, $payload['attempt']);
        $this->assertSame(1, $payload['issue_count']);
        $this->assertSame(0.85, $payload['confidence']);
        $this->assertFalse($payload['shadow_mode']);
    }
}
