<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Models\WorkflowInterrogationAttachment;
use App\Models\WorkflowInterrogationBatch;
use App\Models\WorkflowInterrogationBatchQuestion;
use App\Models\WorkflowInterrogationSession;

class WorkflowInterrogationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function session(WorkflowInterrogationSession $session, bool $includeLargePayloads = false): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $attachments = $session->relationLoaded('attachments')
            ? $session->attachments
            : $session->attachments()->get();
        $activeBatch = $session->relationLoaded('activeBatch')
            ? $session->activeBatch
            : $session->activeBatch()->with(['questions.answer'])->first();

        return [
            'id' => $session->id,
            'user_id' => $session->user_id,
            'name' => $session->name,
            'runner_type' => $session->runner_type,
            'model' => $session->model,
            'project_directory' => $session->project_directory,
            'interrogation_mode' => $session->interrogation_mode,
            'company_name' => $session->company_name,
            'company_description' => $includeLargePayloads ? $session->company_description : null,
            'workflow_title' => $session->workflow_title,
            'workflow_brief' => $includeLargePayloads ? $session->workflow_brief : null,
            'target_teams' => array_values($session->target_teams_json ?? []),
            'systems' => array_values($session->systems_json ?? []),
            'status' => $session->status,
            'phase' => $session->phase,
            'current_round' => (int) $session->current_round,
            'cli_session_id' => $session->cli_session_id,
            'summary_json' => $includeLargePayloads ? $session->summary_json : null,
            'action_plan_json' => $includeLargePayloads ? $session->action_plan_json : null,
            'attachments' => $attachments
                ->map(fn (WorkflowInterrogationAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => (int) $attachment->size_bytes,
                    'has_extracted_text' => trim((string) ($attachment->extracted_text ?? '')) !== '',
                    'download_url' => route('api.workflow-interrogator.attachments.download', [
                        'id' => $session->id,
                        'attachmentId' => $attachment->id,
                    ]),
                    'preview_url' => str_starts_with($attachment->mime_type, 'image/')
                        ? route('api.workflow-interrogator.attachments.download', [
                            'id' => $session->id,
                            'attachmentId' => $attachment->id,
                        ])
                        : null,
                ])->values()->all(),
            'metadata_json' => $metadata,
            'active_batch' => $this->presentBatch($activeBatch, $includeLargePayloads),
            'ambiguity_report' => is_array($metadata['ambiguity_report'] ?? null) ? $metadata['ambiguity_report'] : null,
            'processing' => is_array($metadata['processing'] ?? null) ? $metadata['processing'] : null,
            'error_code' => $session->error_code,
            'error_summary' => $session->error_summary,
            'started_at' => optional($session->started_at)?->toIso8601String(),
            'finished_at' => optional($session->finished_at)?->toIso8601String(),
            'summary_confirmed_at' => optional($session->summary_confirmed_at)?->toIso8601String(),
            'created_at' => optional($session->created_at)?->toIso8601String(),
            'updated_at' => optional($session->updated_at)?->toIso8601String(),
        ];
    }

    private function presentBatch(?WorkflowInterrogationBatch $batch, bool $includeQuestions): ?array
    {
        if (! $batch instanceof WorkflowInterrogationBatch) {
            return null;
        }

        $base = [
            'id' => (int) $batch->id,
            'round' => (int) $batch->round,
            'question_count' => $batch->questions->count(),
            'answered_at' => optional($batch->answered_at)?->toIso8601String(),
        ];

        if (! $includeQuestions) {
            return $base;
        }

        $base['questions'] = $batch->questions
            ->map(fn (WorkflowInterrogationBatchQuestion $question): array => [
                'id' => (int) $question->id,
                'question_id' => $question->question_key,
                'prompt' => $question->prompt,
                'answer_type' => $question->answer_type,
                'options' => array_values($question->options_json ?? []),
                'required' => (bool) $question->is_required,
                'rationale' => $question->rationale,
                'category' => $question->category,
                'answer' => $question->answer === null ? null : [
                    'answer_type' => $question->answer->answer_type,
                    'answer_text' => $question->answer->answer_text,
                    'selected_option' => $question->answer->selected_option,
                    'selected_options' => array_values($question->answer->selected_options_json ?? []),
                    'submitted_at' => optional($question->answer->submitted_at)?->toIso8601String(),
                ],
            ])
            ->values()
            ->all();

        return $base;
    }
}
