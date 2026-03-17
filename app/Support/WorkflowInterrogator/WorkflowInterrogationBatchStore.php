<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Models\WorkflowInterrogationBatch;
use App\Models\WorkflowInterrogationBatchAnswer;
use App\Models\WorkflowInterrogationBatchQuestion;
use App\Models\WorkflowInterrogationSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowInterrogationBatchStore
{
    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    public function createActiveBatch(
        WorkflowInterrogationSession $session,
        int $round,
        array $questions,
    ): WorkflowInterrogationBatch {
        return DB::transaction(function () use ($session, $round, $questions): WorkflowInterrogationBatch {
            WorkflowInterrogationBatch::query()
                ->where('workflow_interrogation_session_id', $session->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $batch = WorkflowInterrogationBatch::query()->create([
                'workflow_interrogation_session_id' => (int) $session->id,
                'round' => $round,
                'is_active' => true,
            ]);

            $usedQuestionKeys = [];

            foreach (array_values($questions) as $position => $question) {
                $questionKey = $this->questionKeyForStorage($question, $position, $usedQuestionKeys);
                $usedQuestionKeys[] = $questionKey;

                WorkflowInterrogationBatchQuestion::query()->create([
                    'workflow_interrogation_batch_id' => (int) $batch->id,
                    'position' => $position,
                    'question_key' => $questionKey,
                    'prompt' => $this->normalizeString($question['prompt'] ?? ''),
                    'answer_type' => (string) ($question['answer_type'] ?? 'freetext'),
                    'options_json' => array_values((array) ($question['options'] ?? [])),
                    'is_required' => (bool) ($question['required'] ?? true),
                    'rationale' => ($rationale = $this->normalizeString($question['rationale'] ?? '')) !== '' ? $rationale : null,
                    'category' => ($category = $this->normalizeString($question['category'] ?? '')) !== '' ? $category : null,
                ]);
            }

            return $batch->fresh('questions') ?? $batch->load('questions');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     */
    public function recordAnswers(WorkflowInterrogationBatch $batch, array $answers): void
    {
        DB::transaction(function () use ($batch, $answers): void {
            $questions = $batch->questions()->get()->keyBy('question_key');
            $submittedAt = CarbonImmutable::now('UTC');

            foreach ($answers as $answer) {
                $questionKey = (string) ($answer['question_id'] ?? '');
                $question = $questions->get($questionKey);

                if (! $question instanceof WorkflowInterrogationBatchQuestion) {
                    continue;
                }

                WorkflowInterrogationBatchAnswer::query()->updateOrCreate(
                    ['workflow_interrogation_batch_question_id' => (int) $question->id],
                    [
                        'answer_type' => (string) ($answer['answer_type'] ?? 'freetext'),
                        'answer_text' => isset($answer['answer_text']) ? trim((string) $answer['answer_text']) : null,
                        'selected_option' => isset($answer['selected_option']) ? trim((string) $answer['selected_option']) : null,
                        'selected_options_json' => array_values(array_filter(
                            (array) ($answer['selected_options'] ?? []),
                            static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
                        )),
                        'submitted_at' => $submittedAt,
                    ]
                );
            }

            $batch->forceFill([
                'is_active' => false,
                'answered_at' => $submittedAt,
            ])->save();
        });
    }

    public function activeBatchForSession(WorkflowInterrogationSession $session): ?WorkflowInterrogationBatch
    {
        return $session->activeBatch()->with(['questions.answer'])->first();
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<int, string>  $usedQuestionKeys
     */
    private function questionKeyForStorage(array $question, int $position, array $usedQuestionKeys): string
    {
        $candidate = $this->normalizeString($question['question_id'] ?? '');
        if ($candidate === '') {
            $candidate = Str::slug($this->normalizeString($question['decision_axis'] ?? ''), '_');
        }

        if ($candidate === '') {
            $candidate = 'q_generated_'.$position;
        }

        if (in_array($candidate, $usedQuestionKeys, true)) {
            $candidate .= '_'.substr(hash('xxh128', json_encode($question, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: (string) $position), 0, 8);
        }

        return $candidate;
    }

    private function normalizeString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
