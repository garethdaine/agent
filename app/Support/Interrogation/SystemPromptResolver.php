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
            'interrogation' => 'Phase: interrogation. Return ONLY a single JSON object that matches the provided schema. Ask exactly one high-signal question.',
            'summary' => 'Phase: summary. Return ONLY a single JSON object that matches the provided schema for summary output.',
            'planning' => 'Phase: planning. Return ONLY a single JSON object that matches the provided schema for planning output.',
            default => 'Phase: setup.',
        };

        $featureContext = '';
        if ($session->interrogation_type === InterrogationSession::TYPE_FEATURE && is_string($session->feature_brief) && trim($session->feature_brief) !== '') {
            $featureContext = "\n\nFeature Brief:\n".trim($session->feature_brief);
        }

        return trim($base)."\n\n".$phaseInstructions.$featureContext;
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

Output contract:
- When output format is stream-json, emit short progress updates.
- When output format is json with a schema, return exactly one valid JSON object matching that schema.
PROMPT;
    }
}
