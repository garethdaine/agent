<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Interrogation\QuestionPayloadGuard;
use Tests\TestCase;

class QuestionPayloadGuardTest extends TestCase
{
    public function test_it_accepts_single_choice_question_with_structured_options(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_text' => 'How should retries be bounded for MVP?',
            'answer_type' => 'choice',
            'options' => ['Fixed policy', 'Rules engine', 'Escalation ladder'],
            'progress_estimate' => 20,
            'is_complete' => false,
        ]);

        $this->assertTrue($result['valid']);
    }

    public function test_it_rejects_batched_question_markers(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_text' => 'Resolving open questions — batch 1 of 4. Q1: Capabilities? Q2: Metrics?',
            'answer_type' => 'freetext',
            'options' => [],
            'progress_estimate' => 20,
            'is_complete' => false,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function test_it_rejects_choice_without_structured_options(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_text' => 'Choose one approach.',
            'answer_type' => 'choice',
            'options' => ['One'],
            'progress_estimate' => 20,
            'is_complete' => false,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function test_it_rejects_operational_status_text_disguised_as_question(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_text' => 'Resuming interrogation phase by locating the latest saved unanswered question in this workspace/session state.',
            'answer_type' => 'freetext',
            'options' => [],
            'progress_estimate' => 5,
            'is_complete' => false,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertSame('operational status text was returned instead of a user-answerable question', $result['reason']);
    }

    public function test_it_accepts_real_question_about_session_state(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_text' => 'Which parts of session state should persist across retries?',
            'answer_type' => 'freetext',
            'options' => [],
            'progress_estimate' => 10,
            'is_complete' => false,
        ]);

        $this->assertTrue($result['valid']);
    }

    public function test_it_rejects_verbatim_resume_question_replay_request(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_id' => 'q-replay',
            'question_text' => 'Please paste the exact full text (verbatim) of the latest resumed interrogation question that is still unanswered.',
            'answer_type' => 'freetext',
            'options' => [],
            'reasoning' => '',
            'category' => 'open-question',
            'progress_estimate' => 12,
            'is_complete' => false,
            'cli_session_id' => 's-1',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('operational status text', $result['reason']);
    }

    public function test_it_rejects_verbatim_answer_replay_request(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_id' => 'q-answer-replay',
            'question_text' => "Please provide the user's exact answer text for that question (verbatim, including punctuation/emojis if any).",
            'answer_type' => 'freetext',
            'options' => [],
            'reasoning' => '',
            'category' => 'open-question',
            'progress_estimate' => 25,
            'is_complete' => false,
            'cli_session_id' => 's-1',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('operational status text', $result['reason']);
    }

    public function test_it_rejects_prior_answer_content_replay_request_without_verbatim_wording(): void
    {
        $guard = new QuestionPayloadGuard;

        $result = $guard->validate([
            'question_id' => 'q-answer-content-replay',
            'question_text' => "What is the user's actual answer content for that question?",
            'answer_type' => 'freetext',
            'options' => [],
            'reasoning' => '',
            'category' => 'open-question',
            'progress_estimate' => 25,
            'is_complete' => false,
            'cli_session_id' => 's-1',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('operational status text', $result['reason']);
    }
}
