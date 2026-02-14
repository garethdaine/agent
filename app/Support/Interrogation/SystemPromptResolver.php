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
            'discovery' => 'Phase: discovery. Inspect project context and report concise discoveries only.',
            'interrogation' => 'Phase: interrogation. Ask one high-signal question at a time with clear answer format.',
            'summary' => 'Phase: summary. Produce structured summary JSON for user confirmation.',
            'planning' => 'Phase: planning. Produce implementable, sequenced plan JSON with clear acceptance criteria.',
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

        $fallbackPath = base_path('docs/interrogate.md');
        if (is_file($fallbackPath)) {
            $contents = @file_get_contents($fallbackPath);
            if (is_string($contents) && trim($contents) !== '') {
                return $contents;
            }
        }

        return 'You are a requirements discovery assistant. Ask focused, structured questions and produce implementable outputs.';
    }
}
