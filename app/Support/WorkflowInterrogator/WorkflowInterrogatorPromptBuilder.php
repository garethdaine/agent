<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Models\WorkflowInterrogationAttachment;
use App\Models\WorkflowInterrogationSession;

class WorkflowInterrogatorPromptBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $latestAnswers
     */
    public function buildRoundPrompt(
        WorkflowInterrogationSession $session,
        array $history,
        array $latestAnswers = [],
    ): string {
        $historyJson = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        $answersJson = json_encode($latestAnswers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        $teamsJson = json_encode(array_values($session->target_teams_json ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        $systemsJson = json_encode(array_values($session->systems_json ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        $attachmentsContext = $this->attachmentsContext($session);

        return <<<PROMPT
You are a ruthless workflow requirements interrogator for operations-heavy businesses.

Hard rules:
- Do not write code.
- Do not suggest implementation solutions prematurely.
- Ask exhaustive questions until assumptions and ambiguity are eliminated.
- Start from the user brief and uploaded context first.
- The interrogation loop is finite per round, not finite overall.
- For this response, generate only the next finite batch for the current round.
- If ambiguity remains, set needs_another_round=true and return 3-8 questions.
- If ambiguity is materially closed, return zero questions and set needs_another_round=false.
- Questions must be direct and user-answerable.
- Use answer_type="choice" or "multi_choice" when the space is finite.
- Use answer_type="freetext" only when necessary.
- If a prior answer is vague, generate clarifying questions instead of accepting it.
- Do not default to codebase or repository inspection.
- The selected working folder is optional supporting context only. Use it only if the brief or uploaded context implies it contains relevant material.
- Uploaded files and images are part of the session context and should be used when relevant.

You are following this process:
1. understand scope
2. build context
3. interrogate in batches
4. detect ambiguity, contradictions, and gaps
5. continue until closure
6. produce structured summary only when ambiguity is closed

Return JSON only.

Session context:
- Company: {$session->company_name}
- Company description: {$session->company_description}
- Workflow title: {$session->workflow_title}
- Workflow brief: {$session->workflow_brief}
- Interrogation mode: {$session->interrogation_mode}
- Selected working folder: {$session->project_directory}
- Target teams: {$teamsJson}
- Systems: {$systemsJson}
- Current round: {$session->current_round}

Uploaded session context:
{$attachmentsContext}

Prior history:
{$historyJson}

Latest submitted answers:
{$answersJson}

Return a JSON object with this exact shape:
{
  "questions": [
    {
      "question_id": "string",
      "prompt": "string",
      "answer_type": "choice|multi_choice|freetext",
      "options": ["string"],
      "required": true,
      "rationale": "string",
      "category": "string"
    }
  ],
  "ambiguity_report": {
    "needs_another_round": true,
    "resolved_areas": ["string"],
    "open_ambiguities": ["string"],
    "contradictions": ["string"],
    "coverage_gaps": ["string"],
    "closure_reason": "string"
  },
  "summary": {
    "summary_markdown": "string",
    "goals": ["string"],
    "actors": ["string"],
    "systems": ["string"],
    "constraints": ["string"],
    "risks": ["string"],
    "notes": ["string"]
  },
  "cli_session_id": "string"
}
PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    public function buildActionPlanPrompt(
        WorkflowInterrogationSession $session,
        array $history,
        array $summary,
    ): string {
        $historyJson = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
        $summaryJson = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $attachmentsContext = $this->attachmentsContext($session);

        return <<<PROMPT
Generate an implementation-oriented action plan for this workflow discovery outcome.

Hard rules:
- Be pragmatic and tool-agnostic.
- Recommend the fastest reliable path to value.
- Include a pilot wedge and recommended tooling direction.
- Include risks and assumptions explicitly.
- Do not produce code.

Session context:
- Company: {$session->company_name}
- Workflow title: {$session->workflow_title}
- Workflow brief: {$session->workflow_brief}
- Selected working folder: {$session->project_directory}

Uploaded session context:
{$attachmentsContext}

Discovery history:
{$historyJson}

Confirmed summary:
{$summaryJson}

Return JSON only in this exact shape:
{
  "action_plan_markdown": "string",
  "recommended_approach": "string",
  "recommended_tooling": ["string"],
  "pilot_recommendation": "string",
  "phases": ["string"],
  "risks": ["string"],
  "assumptions": ["string"]
}
PROMPT;
    }

    private function attachmentsContext(WorkflowInterrogationSession $session): string
    {
        $attachments = $session->relationLoaded('attachments')
            ? $session->attachments
            : $session->attachments()->get();

        if ($attachments->isEmpty()) {
            return '- No uploaded session files or images.';
        }

        $parts = [];
        $remainingBudget = 12000;

        foreach ($attachments as $attachment) {
            if (! $attachment instanceof WorkflowInterrogationAttachment) {
                continue;
            }

            $absolutePath = storage_path('app/private/'.$attachment->storage_path);
            $parts[] = sprintf(
                '- %s (%s, %d bytes) at %s',
                $attachment->filename,
                $attachment->mime_type,
                (int) $attachment->size_bytes,
                $absolutePath
            );

            $text = trim((string) ($attachment->extracted_text ?? ''));
            if ($text === '' || $remainingBudget <= 0) {
                continue;
            }

            $excerpt = mb_substr($text, 0, min(4000, $remainingBudget));
            $remainingBudget -= mb_strlen($excerpt);
            $parts[] = "  Extracted text excerpt:\n".$excerpt;
        }

        return implode("\n", $parts);
    }
}
