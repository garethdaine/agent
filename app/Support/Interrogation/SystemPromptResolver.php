<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;

class SystemPromptResolver
{
    public function resolveForPhase(InterrogationSession $session, string $phase): string
    {
        $base = $this->basePrompt($session);

        $phaseInstructions = match ($phase) {
            'discovery' => 'Phase: discovery. Inspect project context and emit concise human-readable progress lines only.',
            'interrogation' => 'Phase: interrogation. Return ONLY a single JSON object that matches the provided schema. Ask exactly one high-signal question (never batch multiple questions). '
                .'For choice questions set answer_type="choice" and provide options as a structured string array. '
                .'Do not include option blocks inside question_text. '
                .'Never output process-status narration (for example "Resuming interrogation", "locating latest unanswered question", "loading session state"). '
                .'question_text must be a direct, user-answerable question about product requirements.',
            'summary' => 'Phase: summary. Return ONLY a single JSON object that matches the provided schema for summary output. '
                .'Never include estimates or timeline projections (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule).',
            'planning' => 'Phase: planning. Return ONLY a single JSON object that matches the provided schema for planning output. '
                .'Never include estimates or timeline projections (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule).',
            'build_tasks' => 'Phase: build task generation. Return ONLY a single JSON object that matches the provided schema for executable build tasks.',
            default => 'Phase: setup.',
        };

        $featureContext = '';
        if ($session->interrogation_type === InterrogationSession::TYPE_FEATURE && is_string($session->feature_brief) && trim($session->feature_brief) !== '') {
            $featureContext = "\n\nFeature Brief:\n".trim($session->feature_brief);
        }

        $runnerInstructions = $this->runnerInstructions($session, $phase);

        return trim($base)."\n\n".$phaseInstructions.$runnerInstructions.$featureContext;
    }

    private function runnerInstructions(InterrogationSession $session, string $phase): string
    {
        if ((string) $session->runner_type !== 'codex') {
            return '';
        }

        return match ($phase) {
            'interrogation' => "\n\nCodex parity rules: "
                .'Match the depth/precision expected from Claude sessions. '
                .'Prefer answer_type="choice" with 3-5 concrete options whenever the decision space is finite. '
                .'Only use answer_type="freetext" when options would be speculative. '
                .'Do not set is_complete=true until ambiguity is materially closed for scope, contracts, auth, lifecycle, errors, and testing expectations.',
            'planning' => "\n\nCodex parity rules: "
                .'Plan output must be production-ready and detailed; avoid thin summaries. '
                .'Use specific implementation steps and concrete technical choices.',
            default => '',
        };
    }

    private function basePrompt(InterrogationSession $session): string
    {
        $setting = InterrogationSetting::getForUser((int) $session->user_id, 'interrogation.system_prompt');

        if (is_string($setting) && trim($setting) !== '') {
            return $setting;
        }

        if (is_array($setting) && isset($setting['text']) && is_string($setting['text']) && trim($setting['text']) !== '') {
            return $setting['text'];
        }

        return <<<'PROMPT'
You are Agent's requirements discovery runtime in non-interactive CLI mode.

Hard rules:
- Do not reference unavailable orchestration tools (for example AskUserQuestion or EnterPlanMode).
- Do not output markdown wrappers or prose outside required output format.
- Prefer concise outputs and avoid unnecessary tool calls.
- If a tool error is transient, recover and continue; do not loop indefinitely.
- During interrogation, ask one question per turn only. Never batch Q1/Q2/Q3 in one payload.

Output contract:
- When output format is stream-json, emit short progress updates.
- When output format is json with a schema, return exactly one valid JSON object matching that schema.
PROMPT;
    }
}
