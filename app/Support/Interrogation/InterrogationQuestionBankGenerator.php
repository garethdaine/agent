<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use Symfony\Component\Process\Process;

class InterrogationQuestionBankGenerator
{
    public function __construct(
        private readonly InterrogationQuestionBankPlanner $planner,
    ) {}

    /**
     * @return array{questions:array<int, array<string,mixed>>,cli_session_id:?string}|null
     */
    public function generate(
        InterrogationSession $session,
        InterrogationRunnerAdapter $adapter,
        string $systemPrompt,
    ): ?array {
        $prompt = $this->buildPrompt($session);
        $command = $adapter->buildQuestionBankCommand($session, $prompt, $systemPrompt);
        $env = $adapter->buildEnvironment($session);

        $process = new Process($command, (string) $session->project_directory, $env);
        $process->setTimeout(600);
        $process->run();

        if ((int) ($process->getExitCode() ?? 1) !== 0) {
            return null;
        }

        $parsed = $adapter->parseQuestionBankResponse((string) $process->getOutput());
        if (! is_array($parsed)) {
            return null;
        }

        $rawQuestions = is_array($parsed['questions'] ?? null)
            ? $parsed['questions']
            : [];

        $normalized = $this->planner->normalize($rawQuestions);
        if ($normalized === []) {
            return null;
        }

        $maxCount = max(3, (int) config('agent.interrogation.dynamic_question_bank_max_questions', 18));
        $questions = array_slice($normalized, 0, $maxCount);

        return [
            'questions' => $questions,
            'cli_session_id' => is_string($parsed['cli_session_id'] ?? null)
                ? $parsed['cli_session_id']
                : null,
        ];
    }

    private function buildPrompt(InterrogationSession $session): string
    {
        $brief = trim((string) ($session->feature_brief ?? ''));

        $findings = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_DISCOVERY_ACTIVITY)
            ->orderByDesc('sequence')
            ->limit(30)
            ->get(['payload'])
            ->map(function (InterrogationEvent $event): string {
                $payload = is_array($event->payload) ? $event->payload : [];

                return trim((string) ($payload['message'] ?? ''));
            })
            ->filter(static fn (string $message): bool => $message !== '')
            ->reverse()
            ->values()
            ->all();

        $discoverySummary = $findings === []
            ? '- No discovery findings available.'
            : '- '.implode("\n- ", $findings);

        return <<<PROMPT
Build a finite requirements decision graph for this run from the brief and discovery findings.

Rules:
- Create exactly one node per unique decision axis.
- Each node must include `category`, `decision_axis`, `prompt`, `answer_type`, `options`, `depends_on_axes`, `priority`, and `rationale`.
- `decision_axis` must be stable and semantic (e.g., "success_contract", "schema_authority"), not phrasing-dependent.
- Keep dependencies acyclic and only reference earlier prerequisite axes.
- Do not duplicate axes with reworded prompts.
- Use `choice` with 2-5 options when the decision space is finite; otherwise use `freetext` with empty options.
- Keep prompts user-answerable and concise.

Session brief:
{$brief}

Discovery findings:
{$discoverySummary}
PROMPT;
    }
}
