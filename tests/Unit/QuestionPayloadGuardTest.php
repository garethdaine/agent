<?php

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
}
