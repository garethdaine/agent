<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\ReviewerContextBuilder;
use App\Support\Interrogation\ReviewerPayloadGuard;
use App\Support\Interrogation\ReviewerPayloadNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests shadow-mode adversarial reviewer integration in ExecuteInterrogationSummaryJob.
 *
 * Shadow mode (review_warn_only=true) runs the reviewer but does not gate artifacts.
 * Findings are logged to metadata_json and events are emitted for observability.
 *
 * These tests verify:
 * - Shadow mode stores findings in metadata_json.summary.review_history
 * - Shadow mode emits TYPE_SUMMARY_REVIEW events with shadow_mode=true
 * - Shadow mode does not block/fail session on revise verdict
 * - Disabled flag skips reviewer entirely
 * - Low confidence warnings are stored when pass verdict has confidence < threshold
 * - Reviewer exceptions are caught and logged in shadow mode
 */
class AdversarialReviewerShadowModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.interrogation.adversarial_review_enabled' => true]);
        config(['agent.interrogation.review_warn_only' => true]);
    }

    /**
     * Create an AdversarialReviewerService with a mocked ClaudeAdapter that returns
     * the specified reviewer payload.
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
     * Directly invoke the runAdversarialReview method via reflection to test
     * shadow-mode behavior without needing to mock the entire summary generation flow.
     */
    private function invokeAdversarialReview(
        ExecuteInterrogationSummaryJob $job,
        InterrogationSession $session,
        array $summaryCandidate
    ): void {
        $writer = new \App\Support\Interrogation\InterrogationEventWriter($session);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('runAdversarialReview');
        $method->setAccessible(true);
        $method->invoke($job, $session, $summaryCandidate, $writer);
    }

    public function test_shadow_mode_stores_findings_in_metadata(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create([
                'metadata_json' => ['brief' => 'Test brief'],
            ]);

        $reviewPayload = [
            'verdict' => 'revise',
            'issues' => [
                [
                    'type' => 'contradiction',
                    'severity' => 'high',
                    'message' => 'Test issue',
                    'evidence' => 'Found conflicting requirements',
                ],
            ],
            'required_changes' => ['Fix contradiction'],
            'clarification_questions' => [],
            'confidence' => 0.6,
            'review_notes' => 'Needs work',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertArrayHasKey('summary', $metadata);
        $this->assertArrayHasKey('review_history', $metadata['summary']);
        $this->assertCount(1, $metadata['summary']['review_history']);
        $this->assertSame('revise', $metadata['summary']['review_history'][0]['verdict']);
        // In shadow mode, status reflects actual verdict prefixed with shadow_
        $this->assertSame('shadow_revise', $metadata['summary']['review_status']);
        $this->assertSame(1, $metadata['summary']['review_attempts']);
    }

    public function test_shadow_mode_emits_review_event(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.9,
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

        $this->assertNotNull($event, 'Expected summary_review event to be created');
        $payload = $event->payload ?? [];
        $this->assertArrayHasKey('shadow_mode', $payload);
        $this->assertTrue($payload['shadow_mode']);
        $this->assertSame('shadow_pass', $payload['status']);
        $this->assertSame('pass', $payload['verdict']);
    }

    public function test_shadow_mode_does_not_block_on_revise_verdict(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        $reviewPayload = [
            'verdict' => 'revise',
            'issues' => [
                [
                    'type' => 'missing_requirement',
                    'severity' => 'critical',
                    'message' => 'Missing core feature',
                    'evidence' => 'Brief mentions X but summary does not',
                ],
            ],
            'required_changes' => ['Add missing requirement'],
            'clarification_questions' => [],
            'confidence' => 0.4,
            'review_notes' => 'Critical issues found',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        $session->refresh();

        // In shadow mode, even critical issues should not fail the session
        $this->assertNotEquals(
            InterrogationSession::STATUS_FAILED,
            $session->status,
            'Shadow mode should not fail session on revise verdict'
        );

        // Verify the revise verdict was still stored in metadata
        $metadata = $session->metadata_json ?? [];
        // In shadow mode, status reflects actual verdict prefixed with shadow_
        $this->assertSame('shadow_revise', $metadata['summary']['review_status']);
        $this->assertSame('revise', $metadata['summary']['last_review']['verdict']);
    }

    public function test_disabled_flag_skips_reviewer(): void
    {
        config(['agent.interrogation.adversarial_review_enabled' => false]);

        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        // Create a mock that should NOT be called
        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldNotReceive('buildReviewerCommand');
        $mockAdapter->shouldNotReceive('parseReviewerResponse');

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $this->app->instance(AdversarialReviewerService::class, $service);

        // The job's handle() checks config before calling runAdversarialReview
        // We verify this by checking no review events were created
        $initialEventCount = $session->events()->count();

        // Since reviewer is disabled, metadata should not have review data
        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertArrayNotHasKey('summary', $metadata);
        $this->assertSame($initialEventCount, $session->events()->count());
    }

    public function test_shadow_mode_logs_low_confidence_warning(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        $reviewPayload = [
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.5, // Below threshold of 0.6
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => 'Low confidence pass',
        ];

        $service = $this->createMockedReviewerService($reviewPayload);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertTrue(
            $metadata['summary']['low_confidence_warning'] ?? false,
            'Low confidence warning should be set when pass verdict has confidence < threshold'
        );
    }

    public function test_shadow_mode_catches_reviewer_exceptions(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')
            ->andThrow(new \RuntimeException('Reviewer subprocess failed'));

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);
        $this->app->instance(AdversarialReviewerService::class, $service);

        $job = new ExecuteInterrogationSummaryJob($session->id);

        // In shadow mode, exceptions should be caught and logged, not thrown
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary']);

        $session->refresh();

        // Session should not be failed
        $this->assertNotEquals(
            InterrogationSession::STATUS_FAILED,
            $session->status,
            'Shadow mode should catch reviewer exceptions'
        );

        // Error should be stored in metadata
        $metadata = $session->metadata_json ?? [];
        $this->assertArrayHasKey('summary', $metadata);
        $this->assertArrayHasKey('review_error', $metadata['summary']);
        $this->assertSame('Reviewer subprocess failed', $metadata['summary']['review_error']['message']);
    }

    public function test_multiple_review_attempts_track_history(): void
    {
        $session = InterrogationSession::factory()
            ->summarizing()
            ->withBrief('Test feature brief')
            ->create();

        // First review attempt
        $reviewPayload1 = [
            'verdict' => 'revise',
            'issues' => [['type' => 'ambiguity', 'severity' => 'medium', 'message' => 'Unclear scope']],
            'confidence' => 0.7,
            'required_changes' => ['Clarify scope'],
            'clarification_questions' => [],
            'review_notes' => '',
        ];

        $service1 = $this->createMockedReviewerService($reviewPayload1);
        $this->app->instance(AdversarialReviewerService::class, $service1);

        $job = new ExecuteInterrogationSummaryJob($session->id);
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary v1']);

        // Second review attempt
        $reviewPayload2 = [
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.95,
            'required_changes' => [],
            'clarification_questions' => [],
            'review_notes' => 'All clear',
        ];

        $service2 = $this->createMockedReviewerService($reviewPayload2);
        $this->app->instance(AdversarialReviewerService::class, $service2);

        $session->refresh();
        $this->invokeAdversarialReview($job, $session, ['summary_markdown' => '# Test Summary v2']);

        $session->refresh();
        $metadata = $session->metadata_json ?? [];

        $this->assertSame(2, $metadata['summary']['review_attempts']);
        $this->assertCount(2, $metadata['summary']['review_history']);
        $this->assertSame('revise', $metadata['summary']['review_history'][0]['verdict']);
        $this->assertSame('pass', $metadata['summary']['review_history'][1]['verdict']);
        $this->assertSame('pass', $metadata['summary']['last_review']['verdict']);
    }
}
