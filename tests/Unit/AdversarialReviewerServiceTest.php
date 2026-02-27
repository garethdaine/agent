<?php

namespace Tests\Unit;

use App\Models\InterrogationSession;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\ReviewerContextBuilder;
use App\Support\Interrogation\ReviewerPayloadGuard;
use App\Support\Interrogation\ReviewerPayloadNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdversarialReviewerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_summary_returns_normalized_payload(): void
    {
        $session = InterrogationSession::factory()->create([
            'project_directory' => '/tmp/test',
        ]);

        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')->andReturn([
            'verdict' => 'pass',
            'issues' => [],
            'required_changes' => [],
            'clarification_questions' => [],
            'confidence' => 0.9,
            'review_notes' => '',
        ]);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );

        // Mock the subprocess execution
        $service->setTestMode(true);

        $result = $service->reviewSummary($session, ['summary_markdown' => '# Test']);

        $this->assertSame('pass', $result['verdict']);
        $this->assertSame(0.9, $result['confidence']);
    }

    public function test_review_plan_rejects_needs_clarification(): void
    {
        $session = InterrogationSession::factory()->create([
            'project_directory' => '/tmp/test',
        ]);

        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')->andReturn([
            'verdict' => 'needs_clarification',
            'issues' => [],
            'clarification_questions' => ['Q1'],
            'confidence' => 0.5,
        ]);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);

        $this->expectException(\InvalidArgumentException::class);
        $service->reviewPlan($session, ['plan_markdown' => '# Plan'], ['summary_markdown' => '# Summary']);
    }

    public function test_invalid_payload_throws_exception(): void
    {
        $session = InterrogationSession::factory()->create([
            'project_directory' => '/tmp/test',
        ]);

        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')->andReturn(['echo', 'test']);
        $mockAdapter->shouldReceive('parseReviewerResponse')->andReturn([
            'verdict' => 'invalid_verdict',
            'issues' => [],
            'confidence' => 0.5,
        ]);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);

        $this->expectException(\InvalidArgumentException::class);
        $service->reviewSummary($session, ['summary_markdown' => '# Test']);
    }

    public function test_context_package_passed_to_prompt(): void
    {
        $session = InterrogationSession::factory()->create([
            'project_directory' => '/tmp/test',
            'metadata_json' => ['brief' => 'Feature brief content'],
        ]);

        $promptCapture = null;
        $mockAdapter = Mockery::mock(ClaudeAdapter::class);
        $mockAdapter->shouldReceive('buildReviewerCommand')
            ->andReturnUsing(function ($dir, $prompt) use (&$promptCapture) {
                $promptCapture = $prompt;

                return ['echo', 'test'];
            });
        $mockAdapter->shouldReceive('parseReviewerResponse')->andReturn([
            'verdict' => 'pass',
            'issues' => [],
            'confidence' => 0.9,
        ]);

        $service = new AdversarialReviewerService(
            $mockAdapter,
            new ReviewerPayloadGuard,
            new ReviewerPayloadNormalizer,
            new ReviewerContextBuilder
        );
        $service->setTestMode(true);

        $service->reviewSummary($session, ['summary_markdown' => '# Summary']);

        $this->assertStringContainsString('Feature brief content', $promptCapture);
    }
}
