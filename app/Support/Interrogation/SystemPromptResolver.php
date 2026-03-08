<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;
use App\Support\Agent\EngineeringRulesInjector;
use Illuminate\Support\Facades\Log;

class SystemPromptResolver
{
    public function __construct(
        private ?PromptIsolationService $promptIsolation = null,
    ) {}

    public function resolveForPhase(InterrogationSession $session, string $phase): string
    {
        $base = $this->basePrompt($session);

        $phaseInstructions = match ($phase) {
            'discovery' => 'Phase: discovery. Inspect project context and emit concise human-readable progress lines only.',
            'interrogation' => 'Phase: interrogation. Return ONLY a single JSON object that matches the provided schema. Ask exactly one high-signal question (never batch multiple questions). '
                .'For choice questions set answer_type="choice" and provide options as a structured string array. '
                .'Do not include option blocks inside question_text. '
                .'Populate reasoning with a concise, user-safe explanation (1-3 sentences) of why this question matters. '
                .'Never output process-status narration (for example "Resuming interrogation", "locating latest unanswered question", "loading session state"). '
                .'question_text must be a direct, user-answerable question about product requirements.',
            'summary' => 'Phase: summary. Return ONLY a single JSON object that matches the provided schema for summary output. '
                .'Never include estimates or timeline projections (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule).',
            'planning' => 'Phase: planning. Return ONLY a single JSON object that matches the provided schema for planning output. '
                .'Never include estimates or timeline projections (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule).',
            'build_tasks' => 'Phase: build task generation. Return ONLY a single JSON object that matches the provided schema for executable build tasks. '
                .'Every implementation task must enforce tests first: write or update tests before feature/refactor code, verify the tests fail for the intended reason, then implement the smallest clean change until tests pass. '
                .'Apply Code Field rules in every task: state assumptions before coding, do not claim correctness without verification, do not handle only the happy path, and explicitly document conditions where the approach works. '
                .'When capability is user-facing/operator-facing, require explicit route/page/navigation wiring and in-app discoverability verification in tasks.',
            default => 'Phase: setup.',
        };

        $runnerInstructions = $this->runnerInstructions($session, $phase);
        $sessionContext = $this->sessionContext($session, $phase);

        $result = trim($base)."\n\n".$phaseInstructions.$runnerInstructions.$sessionContext;

        // Engineering rules injection for interrogation system prompt (non-blocking)
        try {
            $rulesInjector = app(EngineeringRulesInjector::class);
            if ($rulesInjector->isEnabled()) {
                $profileKey = 'agent.engineering_rules.profiles.interrogation_'.$phase;
                $rulesProfile = (string) config($profileKey, 'interrogation');
                $result = $rulesInjector->injectIntoSystemPrompt($result, $rulesProfile);
            }
        } catch (\Throwable $e) {
            Log::warning('Engineering rules injection failed for interrogation', [
                'session_id' => $session->id,
                'phase' => $phase,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    private function sessionContext(InterrogationSession $session, string $phase): string
    {
        $type = trim((string) $session->interrogation_type);
        $brief = is_string($session->feature_brief) ? trim($session->feature_brief) : '';

        $typeLabel = match ($type) {
            InterrogationSession::TYPE_FEATURE => 'feature',
            InterrogationSession::TYPE_GENERAL => 'general',
            default => $type !== '' ? $type : 'unknown',
        };

        $context = "\n\nSession Context:\nInterrogation Type: {$typeLabel}";

        if ($brief !== '') {
            if ($this->promptIsolation !== null) {
                $context .= "\n\n".$this->promptIsolation->wrapFeatureBrief($brief);
            } else {
                $briefLabel = $type === InterrogationSession::TYPE_FEATURE ? 'Feature Brief' : 'Session Brief';
                $context .= "\n\n{$briefLabel}:\n{$brief}";
            }
        }

        $techStacks = $session->techStacks()->ordered()->get(['name', 'documentation_url']);
        if ($techStacks->isNotEmpty()) {
            $techStackString = '';

            foreach ($techStacks as $stack) {
                $name = trim((string) $stack->name);
                $documentationUrl = trim((string) $stack->documentation_url);

                if ($name === '' || $documentationUrl === '') {
                    continue;
                }

                $techStackString .= sprintf("- %s: %s\n", $name, $documentationUrl);
            }

            $techStackString = rtrim($techStackString);

            if ($techStackString !== '') {
                if ($this->promptIsolation !== null) {
                    $context .= "\n\n".$this->promptIsolation->wrapTechStack($techStackString);
                } else {
                    $context .= "\n\nSelected Tech Stack:\n".$techStackString;
                }
            }
        }

        if ($phase !== 'discovery') {
            $discoveryFindings = $this->recentDiscoveryFindings($session);
            if ($discoveryFindings !== []) {
                if ($this->promptIsolation !== null) {
                    $context .= "\n\n".$this->promptIsolation->wrapDiscoveryFindings($discoveryFindings);
                } else {
                    $context .= "\n\nRecent Discovery Findings:\n- ".implode("\n- ", $discoveryFindings);
                }
            }
        }

        return $context;
    }

    /**
     * @return array<int, string>
     */
    private function recentDiscoveryFindings(InterrogationSession $session): array
    {
        return $session->events()
            ->where('event_type', InterrogationEvent::TYPE_DISCOVERY_ACTIVITY)
            ->orderByDesc('sequence')
            ->limit(12)
            ->get(['payload'])
            ->map(function (InterrogationEvent $event): string {
                $payload = is_array($event->payload) ? $event->payload : [];
                $message = trim((string) ($payload['message'] ?? ''));

                if ($message === '') {
                    return '';
                }

                if (mb_strlen($message) > 220) {
                    return rtrim(mb_substr($message, 0, 220)).'…';
                }

                return $message;
            })
            ->filter(static fn (string $message): bool => $message !== '')
            ->unique()
            ->reverse()
            ->values()
            ->all();
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
            if ($this->promptIsolation !== null) {
                $setting = $this->promptIsolation->validateUserPrompt($setting);
            }

            return $setting;
        }

        if (is_array($setting) && isset($setting['text']) && is_string($setting['text']) && trim($setting['text']) !== '') {
            $text = $setting['text'];

            if ($this->promptIsolation !== null) {
                $text = $this->promptIsolation->validateUserPrompt($text);
            }

            return $text;
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
