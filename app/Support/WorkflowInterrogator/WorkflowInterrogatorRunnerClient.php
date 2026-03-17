<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Models\InterrogationSession;
use App\Models\WorkflowInterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\WorkflowInterrogator\Contracts\WorkflowInterrogatorClient;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class WorkflowInterrogatorRunnerClient implements WorkflowInterrogatorClient
{
    public function __construct(
        private readonly AdapterFactory $adapterFactory,
        private readonly WorkflowInterrogatorPromptBuilder $promptBuilder,
    ) {}

    public function generateRound(WorkflowInterrogationSession $session, array $history, array $latestAnswers = []): array
    {
        $adapterSession = $this->toAdapterSession($session);
        $adapter = $this->adapterFactory->make($session->runner_type, $session->model);
        $prompt = $this->promptBuilder->buildRoundPrompt($session, $history, $latestAnswers);
        $command = $adapter->buildQuestionBankCommand($adapterSession, $prompt, '');
        $process = new Process($command, $session->project_directory, $adapter->buildEnvironment($adapterSession));
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($this->buildProcessErrorMessage($process));
        }

        $output = $adapter->collectProcessOutput($process);

        $decoded = $this->decodeStructuredOutput(
            $output,
            ['questions', 'ambiguity_report', 'summary'],
            ['questions', 'ambiguity_report', 'summary', 'cli_session_id']
        );
        if (! is_array($decoded)) {
            throw new RuntimeException($this->buildStructuredOutputErrorMessage(
                'Workflow Interrogator returned invalid round output.',
                $output
            ));
        }

        return [
            'questions' => $this->normalizeQuestions((array) ($decoded['questions'] ?? [])),
            'ambiguity_report' => is_array($decoded['ambiguity_report'] ?? null) ? $decoded['ambiguity_report'] : [],
            'summary' => is_array($decoded['summary'] ?? null) ? $decoded['summary'] : [],
            'cli_session_id' => is_string($decoded['cli_session_id'] ?? null) ? $decoded['cli_session_id'] : null,
        ];
    }

    public function generateActionPlan(WorkflowInterrogationSession $session, array $history, array $summary): array
    {
        $adapterSession = $this->toAdapterSession($session);
        $adapter = $this->adapterFactory->make($session->runner_type, $session->model);
        $prompt = $this->promptBuilder->buildActionPlanPrompt($session, $history, $summary);
        $command = $adapter->buildPlanCommand($adapterSession, $prompt, '');
        $process = new Process($command, $session->project_directory, $adapter->buildEnvironment($adapterSession));
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($this->buildProcessErrorMessage($process));
        }

        $output = $adapter->collectProcessOutput($process);

        $plan = $this->decodeStructuredOutput(
            $output,
            ['action_plan_markdown'],
            ['action_plan_markdown', 'recommended_approach', 'recommended_tooling', 'pilot_recommendation', 'phases', 'risks', 'assumptions', 'cli_session_id']
        );
        if (! is_array($plan)) {
            throw new RuntimeException($this->buildStructuredOutputErrorMessage(
                'Workflow Interrogator returned invalid action plan output.',
                $output
            ));
        }

        return $plan;
    }

    private function toAdapterSession(WorkflowInterrogationSession $session): InterrogationSession
    {
        $adapterSession = new InterrogationSession;
        $adapterSession->id = $session->id;
        $adapterSession->runner_type = $session->runner_type;
        $adapterSession->model = $session->model;
        $adapterSession->project_directory = $session->project_directory;
        $adapterSession->cli_session_id = $session->cli_session_id;

        return $adapterSession;
    }

    private function buildProcessErrorMessage(Process $process): string
    {
        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        return $stderr !== ''
            ? $stderr
            : ($stdout !== '' ? $stdout : 'Workflow interrogator runner execution failed.');
    }

    /**
     * @param  array<int, string>  $requiredKeys
     * @param  array<int, string>  $signalKeys
     * @return array<string, mixed>|null
     */
    private function decodeStructuredOutput(string $output, array $requiredKeys, array $signalKeys): ?array
    {
        $trimmed = trim($output);
        if ($trimmed === '') {
            return null;
        }

        $sessionId = $this->extractSessionIdFromOutput($trimmed);
        $candidates = [];

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $candidates[] = $decoded;
        }

        foreach (preg_split('/\R/', $trimmed) ?: [] as $line) {
            $lineDecoded = json_decode((string) $line, true);
            if (! is_array($lineDecoded)) {
                continue;
            }

            $candidates[] = $lineDecoded;
        }

        if ($candidates === []) {
            return null;
        }

        $structuredCandidates = [];

        foreach ($candidates as $position => $candidate) {
            $this->collectStructuredPayloadCandidate($structuredCandidates, $candidate, $signalKeys, $position * 10);
            $this->collectStructuredPayloadCandidate($structuredCandidates, $candidate['structured_output'] ?? null, $signalKeys, ($position * 10) + 1);
            $this->collectStructuredPayloadCandidate($structuredCandidates, $candidate['result'] ?? null, $signalKeys, ($position * 10) + 2);

            if (is_string($candidate['result'] ?? null) && trim((string) $candidate['result']) !== '') {
                $this->collectStructuredPayloadCandidate(
                    $structuredCandidates,
                    json_decode(trim((string) $candidate['result']), true),
                    $signalKeys,
                    ($position * 10) + 3
                );
            }

            $this->collectStructuredJsonStringsFromCandidate(
                $structuredCandidates,
                $candidate,
                $signalKeys,
                ($position * 10) + 4
            );

            $item = $candidate['item'] ?? null;
            if (! is_array($item)) {
                continue;
            }

            $this->collectStructuredPayloadCandidate($structuredCandidates, $item['structured_output'] ?? null, $signalKeys, ($position * 10) + 5);
            $this->collectStructuredPayloadCandidate($structuredCandidates, $item['result'] ?? null, $signalKeys, ($position * 10) + 6);

            if (is_string($item['result'] ?? null) && trim((string) $item['result']) !== '') {
                $this->collectStructuredPayloadCandidate(
                    $structuredCandidates,
                    json_decode(trim((string) $item['result']), true),
                    $signalKeys,
                    ($position * 10) + 7
                );
            }

            $this->collectStructuredJsonStringsFromCandidate(
                $structuredCandidates,
                $item,
                $signalKeys,
                ($position * 10) + 8
            );
        }

        if ($structuredCandidates === []) {
            return null;
        }

        $decoded = $this->bestStructuredPayload($structuredCandidates, $signalKeys);

        if (in_array('questions', $requiredKeys, true) && array_key_exists('questions', $decoded)) {
            $decoded = $this->normalizeRoundPayload($decoded);
        }

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $decoded)) {
                return null;
            }
        }

        if (! isset($decoded['cli_session_id']) && is_string($sessionId) && $sessionId !== '') {
            $decoded['cli_session_id'] = $sessionId;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function normalizeRoundPayload(array $decoded): array
    {
        $decoded['questions'] = $this->normalizeQuestions((array) ($decoded['questions'] ?? []));

        if (! array_key_exists('ambiguity_report', $decoded) || ! is_array($decoded['ambiguity_report'])) {
            $decoded['ambiguity_report'] = [
                'needs_another_round' => ((array) ($decoded['questions'] ?? [])) !== [],
                'resolved_areas' => [],
                'open_ambiguities' => [],
                'contradictions' => [],
                'coverage_gaps' => [],
                'closure_reason' => ((array) ($decoded['questions'] ?? [])) !== []
                    ? 'Runner returned a question batch without an explicit ambiguity report.'
                    : 'Runner returned no question batch and no explicit ambiguity report.',
            ];
        }

        if (! array_key_exists('summary', $decoded) || ! is_array($decoded['summary'])) {
            $decoded['summary'] = [];
        }

        return $decoded;
    }

    /**
     * @param  array<int, mixed>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestions(array $questions): array
    {
        $normalized = [];
        $usedQuestionIds = [];

        foreach (array_values($questions) as $position => $question) {
            if (! is_array($question)) {
                continue;
            }

            $prompt = $this->normalizeQuestionString($question['prompt'] ?? $question['question_text'] ?? '');
            if ($prompt === '') {
                $prompt = $this->normalizeQuestionString(json_encode($question, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
            }

            $questionId = $this->normalizeQuestionId($question, $prompt, $position, $usedQuestionIds);
            $usedQuestionIds[] = $questionId;

            $normalized[] = [
                'question_id' => $questionId,
                'prompt' => $prompt,
                'answer_type' => $this->normalizeQuestionString($question['answer_type'] ?? 'freetext') ?: 'freetext',
                'options' => array_values(array_filter(
                    array_map(
                        fn (mixed $option): string => $this->normalizeQuestionString($option),
                        (array) ($question['options'] ?? [])
                    ),
                    static fn (string $option): bool => $option !== ''
                )),
                'required' => (bool) ($question['required'] ?? true),
                'rationale' => $this->normalizeQuestionString($question['rationale'] ?? ''),
                'category' => $this->normalizeQuestionString($question['category'] ?? ''),
                'decision_axis' => $this->normalizeQuestionString($question['decision_axis'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<int, string>  $usedQuestionIds
     */
    private function normalizeQuestionId(array $question, string $prompt, int $position, array $usedQuestionIds): string
    {
        $candidate = $this->normalizeQuestionString($question['question_id'] ?? '');
        if ($candidate === '') {
            $axis = $this->normalizeQuestionString($question['decision_axis'] ?? '');
            if ($axis !== '') {
                $candidate = Str::slug($axis, '_');
            }
        }

        if ($candidate === '' && $prompt !== '') {
            $candidate = 'q_'.Str::slug(Str::limit($prompt, 80, ''), '_');
        }

        if ($candidate === '') {
            $candidate = 'q_generated_'.$position;
        }

        $candidate = Str::lower($candidate);
        if (in_array($candidate, $usedQuestionIds, true)) {
            $candidate .= '_'.substr(hash('xxh128', $prompt.'|'.$position), 0, 8);
        }

        return $candidate;
    }

    private function normalizeQuestionString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function buildStructuredOutputErrorMessage(string $prefix, string $output): string
    {
        $excerpt = trim($output);
        if ($excerpt === '') {
            return $prefix;
        }

        $excerpt = preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt;
        $excerpt = mb_substr($excerpt, 0, 400);

        return sprintf('%s Output excerpt: %s', $prefix, $excerpt);
    }

    private function extractSessionIdFromOutput(string $output): ?string
    {
        $decoded = json_decode($output, true);
        if (is_array($decoded) && is_string($decoded['session_id'] ?? null) && $decoded['session_id'] !== '') {
            return $decoded['session_id'];
        }

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $lineDecoded = json_decode($line, true);
            if (is_array($lineDecoded) && is_string($lineDecoded['session_id'] ?? null) && $lineDecoded['session_id'] !== '') {
                return $lineDecoded['session_id'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{payload:array<string, mixed>,position:int}>  $bucket
     * @param  array<int, string>  $signalKeys
     */
    private function collectStructuredPayloadCandidate(array &$bucket, mixed $value, array $signalKeys, int $position): void
    {
        if (! is_array($value) || ! $this->looksLikeStructuredPayload($value, $signalKeys)) {
            return;
        }

        $bucket[] = [
            'payload' => $value,
            'position' => $position,
        ];
    }

    /**
     * @param  array<int, array{payload:array<string, mixed>,position:int}>  $bucket
     * @param  array<string, mixed>  $candidate
     * @param  array<int, string>  $signalKeys
     */
    private function collectStructuredJsonStringsFromCandidate(array &$bucket, array $candidate, array $signalKeys, int $basePosition): void
    {
        foreach (['text', 'message', 'content'] as $offset => $field) {
            $value = $candidate[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $this->collectStructuredPayloadCandidate(
                    $bucket,
                    json_decode(trim($value), true),
                    $signalKeys,
                    $basePosition + $offset
                );

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $partOffset => $part) {
                if (is_string($part) && trim($part) !== '') {
                    $this->collectStructuredPayloadCandidate(
                        $bucket,
                        json_decode(trim($part), true),
                        $signalKeys,
                        $basePosition + $offset + $partOffset + 1
                    );

                    continue;
                }

                if (! is_array($part)) {
                    continue;
                }

                foreach (['text', 'message', 'content'] as $nestedOffset => $nestedField) {
                    $nestedValue = $part[$nestedField] ?? null;
                    if (! is_string($nestedValue) || trim($nestedValue) === '') {
                        continue;
                    }

                    $this->collectStructuredPayloadCandidate(
                        $bucket,
                        json_decode(trim($nestedValue), true),
                        $signalKeys,
                        $basePosition + $offset + $partOffset + $nestedOffset + 1
                    );
                }
            }
        }
    }

    /**
     * @param  array<int, array{payload:array<string, mixed>,position:int}>  $structuredCandidates
     * @param  array<int, string>  $signalKeys
     * @return array<string, mixed>
     */
    private function bestStructuredPayload(array $structuredCandidates, array $signalKeys): array
    {
        usort($structuredCandidates, function (array $left, array $right) use ($signalKeys): int {
            $scoreComparison = $this->structuredPayloadScore($left['payload'], $signalKeys) <=> $this->structuredPayloadScore($right['payload'], $signalKeys);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return $left['position'] <=> $right['position'];
        });

        $best = end($structuredCandidates);

        return is_array($best['payload'] ?? null) ? $best['payload'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $signalKeys
     */
    private function structuredPayloadScore(array $payload, array $signalKeys): int
    {
        $score = 0;

        foreach ($signalKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $score += 10;

            if (is_string($payload[$key])) {
                $score += min(300, mb_strlen(trim((string) $payload[$key])));
            } elseif (is_array($payload[$key])) {
                $score += min(120, count($payload[$key]) * 6);
            } elseif (is_bool($payload[$key])) {
                $score += 1;
            }
        }

        if (array_key_exists('cli_session_id', $payload)) {
            $score += 3;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<int, string>  $signalKeys
     */
    private function looksLikeStructuredPayload(array $decoded, array $signalKeys): bool
    {
        foreach ($signalKeys as $key) {
            if (array_key_exists($key, $decoded)) {
                return true;
            }
        }

        return false;
    }
}
