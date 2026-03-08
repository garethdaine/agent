<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\InterrogationEventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerEventWriterTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(): InterrogationSession
    {
        $user = User::factory()->create();

        return InterrogationSession::create([
            'user_id' => $user->id,
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test',
            'interrogation_type' => 'general',
            'status' => 'interrogating',
        ]);
    }

    public function test_append_summary_review_creates_event(): void
    {
        $session = $this->createSession();
        $writer = new InterrogationEventWriter($session);

        $payload = [
            'status' => 'passed',
            'verdict' => 'pass',
            'attempt' => 1,
            'issue_count' => 0,
            'confidence' => 0.85,
        ];

        $event = $writer->appendSummaryReview($payload);

        $this->assertSame(InterrogationEvent::TYPE_SUMMARY_REVIEW, $event->event_type);
        $this->assertSame('passed', $event->payload['status']);
        $this->assertSame(0.85, $event->payload['confidence']);
        $this->assertArrayHasKey('at', $event->payload);
    }

    public function test_append_plan_review_creates_event(): void
    {
        $session = $this->createSession();
        $writer = new InterrogationEventWriter($session);

        $payload = [
            'status' => 'failed',
            'verdict' => 'revise',
            'attempt' => 2,
            'issue_count' => 3,
            'confidence' => 0.4,
        ];

        $event = $writer->appendPlanReview($payload);

        $this->assertSame(InterrogationEvent::TYPE_PLAN_REVIEW, $event->event_type);
        $this->assertSame('failed', $event->payload['status']);
        $this->assertSame(2, $event->payload['attempt']);
    }

    public function test_summary_review_event_type_constant_exists(): void
    {
        $this->assertSame('summary_review', InterrogationEvent::TYPE_SUMMARY_REVIEW);
    }

    public function test_plan_review_event_type_constant_exists(): void
    {
        $this->assertSame('plan_review', InterrogationEvent::TYPE_PLAN_REVIEW);
    }
}
