<?php

declare(strict_types=1);

namespace Tests\Unit\WorkflowInterrogator;

use App\Events\WorkflowInterrogationSessionUpdated;
use PHPUnit\Framework\TestCase;

class WorkflowInterrogationSessionUpdatedTest extends TestCase
{
    public function test_broadcast_with_truncates_large_question_batches(): void
    {
        $questions = [];

        for ($index = 0; $index < 20; $index++) {
            $questions[] = [
                'question_id' => 'q-'.$index,
                'prompt' => str_repeat('What should happen in this workflow branch? ', 20),
                'answer_type' => 'choice',
                'options' => [
                    str_repeat('Option A ', 20),
                    str_repeat('Option B ', 20),
                    str_repeat('Option C ', 20),
                ],
                'required' => true,
                'rationale' => str_repeat('This clarifies workflow ambiguity. ', 15),
                'category' => 'scope',
            ];
        }

        $event = new WorkflowInterrogationSessionUpdated(42, [
            'session_id' => 42,
            'sequence' => 7,
            'event_type' => 'question_batch',
            'payload' => [
                'questions' => $questions,
                'ambiguity_report' => [
                    'needs_another_round' => true,
                    'open_ambiguities' => ['Large batch still unresolved.'],
                ],
            ],
            'event_ts' => now('UTC')->toIso8601String(),
            'session' => [
                'id' => 42,
                'status' => 'interrogating',
                'phase' => 1,
                'current_round' => 3,
                'summary_json' => ['summary_markdown' => str_repeat('Summary ', 500)],
                'action_plan_json' => ['action_plan_markdown' => str_repeat('Plan ', 500)],
                'metadata_json' => [
                    'active_batch' => [
                        'round' => 3,
                        'questions' => $questions,
                    ],
                    'processing' => ['kind' => 'round', 'state' => 'idle'],
                ],
                'active_batch' => [
                    'round' => 3,
                    'questions' => $questions,
                ],
                'processing' => ['kind' => 'round', 'state' => 'idle'],
            ],
        ]);

        $broadcast = $event->broadcastWith();
        $encoded = json_encode($broadcast, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsArray($broadcast);
        $this->assertTrue((bool) ($broadcast['_truncated'] ?? false));
        $this->assertTrue((bool) data_get($broadcast, 'session.active_batch._truncated'));
        $this->assertSame(20, data_get($broadcast, 'session.active_batch.question_count'));
        $this->assertTrue((bool) data_get($broadcast, 'payload._truncated'));
        $this->assertSame(20, data_get($broadcast, 'payload.question_count'));
        $this->assertTrue((bool) data_get($broadcast, 'session.metadata_json._truncated'));
        $this->assertNotFalse($encoded);
        $this->assertLessThanOrEqual(8192, strlen((string) $encoded));
    }
}
