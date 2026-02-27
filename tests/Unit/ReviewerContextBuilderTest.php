<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\ReviewerContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_for_summary_review_includes_required_keys(): void
    {
        $session = InterrogationSession::factory()->create([
            'metadata_json' => ['brief' => 'Test brief content'],
            'feature_brief' => 'Feature brief from field',
        ]);
        $summaryCandidate = ['summary_markdown' => '# Summary'];

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, $summaryCandidate);

        $this->assertArrayHasKey('session_metadata', $context);
        $this->assertArrayHasKey('brief', $context);
        $this->assertArrayHasKey('qa_transcript', $context);
        $this->assertArrayHasKey('discovery_findings', $context);
        $this->assertArrayHasKey('candidate', $context);
        $this->assertSame($summaryCandidate, $context['candidate']);
    }

    public function test_build_for_plan_review_includes_locked_summary(): void
    {
        $session = InterrogationSession::factory()->create([
            'metadata_json' => ['brief' => 'Test brief'],
        ]);
        $planCandidate = ['plan_markdown' => '# Plan'];
        $lockedSummary = ['summary_markdown' => '# Locked Summary'];

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForPlanReview($session, $planCandidate, $lockedSummary);

        $this->assertArrayHasKey('locked_summary', $context);
        $this->assertSame($lockedSummary, $context['locked_summary']);
        $this->assertSame($planCandidate, $context['candidate']);
    }

    public function test_context_includes_session_id(): void
    {
        $session = InterrogationSession::factory()->create();

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertSame($session->id, $context['session_metadata']['id']);
    }

    public function test_session_metadata_includes_expected_fields(): void
    {
        $session = InterrogationSession::factory()->create([
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'project_directory' => '/test/project/path',
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $metadata = $context['session_metadata'];
        $this->assertSame($session->id, $metadata['id']);
        $this->assertSame(InterrogationSession::TYPE_FEATURE, $metadata['type']);
        $this->assertSame(InterrogationSession::STATUS_SUMMARIZING, $metadata['status']);
        $this->assertSame('/test/project/path', $metadata['project_directory']);
        $this->assertArrayHasKey('created_at', $metadata);
    }

    public function test_brief_extracted_from_feature_brief_field(): void
    {
        $session = InterrogationSession::factory()->create([
            'feature_brief' => 'Brief from feature_brief field',
            'metadata_json' => [],
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertSame('Brief from feature_brief field', $context['brief']);
    }

    public function test_brief_falls_back_to_metadata_json_brief(): void
    {
        $session = InterrogationSession::factory()->create([
            'feature_brief' => null,
            'metadata_json' => ['brief' => 'Brief from metadata'],
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertSame('Brief from metadata', $context['brief']);
    }

    public function test_brief_falls_back_to_metadata_json_feature_brief(): void
    {
        $session = InterrogationSession::factory()->create([
            'feature_brief' => null,
            'metadata_json' => ['feature_brief' => 'Brief from metadata feature_brief'],
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertSame('Brief from metadata feature_brief', $context['brief']);
    }

    public function test_brief_returns_empty_string_when_not_available(): void
    {
        $session = InterrogationSession::factory()->create([
            'feature_brief' => null,
            'metadata_json' => [],
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertSame('', $context['brief']);
    }

    public function test_qa_transcript_extracted_from_question_and_answer_events(): void
    {
        $session = InterrogationSession::factory()->create();
        $session->events()->create([
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'payload' => ['question_id' => 'q1', 'question_text' => 'What is the feature?'],
            'sequence' => 1,
            'event_ts' => now(),
        ]);
        $session->events()->create([
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'payload' => ['question_id' => 'q1', 'answer_text' => 'A user dashboard'],
            'sequence' => 2,
            'event_ts' => now(),
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertIsString($context['qa_transcript']);
        $this->assertStringContainsString('What is the feature?', $context['qa_transcript']);
        $this->assertStringContainsString('A user dashboard', $context['qa_transcript']);
    }

    public function test_qa_transcript_empty_when_no_qa_events(): void
    {
        $session = InterrogationSession::factory()->create();

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertIsString($context['qa_transcript']);
    }

    public function test_discovery_findings_extracted_from_events(): void
    {
        $session = InterrogationSession::factory()->create();
        $session->events()->create([
            'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
            'payload' => ['finding' => 'Important discovery'],
            'sequence' => 1,
            'event_ts' => now(),
        ]);
        $session->events()->create([
            'event_type' => InterrogationEvent::TYPE_SYSTEM,
            'payload' => ['message' => 'System event'],
            'sequence' => 2,
            'event_ts' => now(),
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertIsArray($context['discovery_findings']);
        $this->assertCount(2, $context['discovery_findings']);
    }

    public function test_discovery_findings_empty_when_no_relevant_events(): void
    {
        $session = InterrogationSession::factory()->create();

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertIsArray($context['discovery_findings']);
        $this->assertEmpty($context['discovery_findings']);
    }

    public function test_discovery_findings_limited_to_fifty_events(): void
    {
        $session = InterrogationSession::factory()->create();

        for ($i = 1; $i <= 60; $i++) {
            $session->events()->create([
                'event_type' => InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
                'payload' => ['finding' => "Discovery $i"],
                'sequence' => $i,
                'event_ts' => now(),
            ]);
        }

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertCount(50, $context['discovery_findings']);
    }

    public function test_works_with_null_metadata_json(): void
    {
        $session = InterrogationSession::factory()->create([
            'feature_brief' => null,
            'metadata_json' => null,
        ]);

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForSummaryReview($session, []);

        $this->assertSame('', $context['brief']);
        $this->assertIsArray($context['session_metadata']);
    }

    public function test_plan_review_context_has_all_required_keys(): void
    {
        $session = InterrogationSession::factory()->planning()->create([
            'feature_brief' => 'Build a user authentication system',
        ]);
        $planCandidate = ['plan_markdown' => '# Implementation Plan'];
        $lockedSummary = ['summary_markdown' => '# Requirements Summary'];

        $builder = new ReviewerContextBuilder;
        $context = $builder->buildForPlanReview($session, $planCandidate, $lockedSummary);

        $this->assertArrayHasKey('session_metadata', $context);
        $this->assertArrayHasKey('brief', $context);
        $this->assertArrayHasKey('qa_transcript', $context);
        $this->assertArrayHasKey('discovery_findings', $context);
        $this->assertArrayHasKey('locked_summary', $context);
        $this->assertArrayHasKey('candidate', $context);
    }
}
