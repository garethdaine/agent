<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\Models\InterrogationSession;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Orchestrates adversarial review of summary and plan artifacts.
 *
 * Invokes a Claude subprocess to validate generated artifacts against
 * discovery context, then validates and normalizes the reviewer's response.
 *
 * @see ReviewerPayloadGuard for validation rules
 * @see ReviewerPayloadNormalizer for normalization behavior
 * @see ReviewerContextBuilder for context assembly
 */
final class AdversarialReviewerService
{
    private bool $testMode = false;

    public function __construct(
        private readonly ClaudeAdapter $adapter,
        private readonly ReviewerPayloadGuard $guard,
        private readonly ReviewerPayloadNormalizer $normalizer,
        private readonly ReviewerContextBuilder $contextBuilder,
    ) {}

    /**
     * Enable test mode to skip subprocess execution.
     *
     * When test mode is enabled, the service skips actual CLI subprocess
     * invocation and relies on the mocked adapter's parseReviewerResponse()
     * to return test data.
     */
    public function setTestMode(bool $enabled): void
    {
        $this->testMode = $enabled;
    }

    /**
     * Review a summary candidate against discovery context.
     *
     * @param  InterrogationSession  $session  The session containing discovery context
     * @param  array<string, mixed>  $summaryCandidate  The summary artifact to review
     * @return array<string, mixed> Validated and normalized reviewer payload
     *
     * @throws \InvalidArgumentException if the reviewer payload is invalid
     * @throws RuntimeException if subprocess execution fails
     */
    public function reviewSummary(
        InterrogationSession $session,
        array $summaryCandidate
    ): array {
        $context = $this->contextBuilder->buildForSummaryReview($session, $summaryCandidate);
        $prompt = $this->buildSummaryReviewPrompt($context);

        $rawOutput = $this->executeReviewer($session, $prompt);
        $payload = $this->adapter->parseReviewerResponse($rawOutput);

        $this->guard->validate($payload, 'summary');

        return $this->normalizer->normalize($payload);
    }

    /**
     * Review a plan candidate against locked summary and discovery context.
     *
     * @param  InterrogationSession  $session  The session containing discovery context
     * @param  array<string, mixed>  $planCandidate  The plan artifact to review
     * @param  array<string, mixed>  $lockedSummary  The approved summary that plan must align with
     * @return array<string, mixed> Validated and normalized reviewer payload
     *
     * @throws \InvalidArgumentException if the reviewer payload is invalid (including needs_clarification verdict)
     * @throws RuntimeException if subprocess execution fails
     */
    public function reviewPlan(
        InterrogationSession $session,
        array $planCandidate,
        array $lockedSummary
    ): array {
        $context = $this->contextBuilder->buildForPlanReview($session, $planCandidate, $lockedSummary);
        $prompt = $this->buildPlanReviewPrompt($context);

        $rawOutput = $this->executeReviewer($session, $prompt);
        $payload = $this->adapter->parseReviewerResponse($rawOutput);

        // Guard will reject needs_clarification for plan reviews
        $this->guard->validate($payload, 'plan');

        return $this->normalizer->normalize($payload);
    }

    /**
     * Execute the reviewer subprocess or return mock output in test mode.
     *
     * @throws RuntimeException if subprocess fails
     */
    private function executeReviewer(InterrogationSession $session, string $prompt): string
    {
        // Always call buildReviewerCommand so mocks can capture the prompt
        $command = $this->adapter->buildReviewerCommand(
            $session->project_directory ?? '',
            $prompt
        );

        if ($this->testMode) {
            // In test mode, we skip actual subprocess execution.
            // The mock adapter's parseReviewerResponse handles response simulation.
            // Return valid JSON structure that parseReviewerResponse can process.
            return json_encode([
                'verdict' => 'pass',
                'issues' => [],
                'confidence' => 1.0,
            ]) ?: '{}';
        }

        $process = new Process($command);
        $process->setWorkingDirectory($session->project_directory ?? sys_get_temp_dir());
        $process->setTimeout((float) config('agent.interrogation.build_task_generation_timeout_seconds', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Reviewer subprocess failed: '.$process->getErrorOutput()
            );
        }

        return $process->getOutput();
    }

    /**
     * Build the prompt for summary review.
     *
     * @param  array<string, mixed>  $context  Context package from ReviewerContextBuilder
     */
    private function buildSummaryReviewPrompt(array $context): string
    {
        return sprintf(
            "You are a criteria-based evaluator assessing the quality of a requirements summary.\n\n".
            "## Evaluation Rubric\n\n".
            "Evaluate the summary candidate against these criteria:\n".
            "1. **Completeness**: Does the summary cover all requirements from the feature brief and Q&A?\n".
            "2. **Consistency**: Are there contradictions between the summary and the discovery findings?\n".
            "3. **Clarity**: Are all requirements unambiguous and precisely defined?\n".
            "4. **Testability**: Can each acceptance criterion be mechanically verified?\n".
            "5. **Coverage**: Are edge cases, error scenarios, and boundary conditions addressed?\n\n".
            "## Context\n\n".
            "### Feature Brief\n%s\n\n".
            "### Q&A Transcript\n%s\n\n".
            "### Discovery Findings\n%s\n\n".
            "### Summary Candidate\n%s\n\n".
            "## Instructions\n\n".
            "Score the candidate against each rubric criterion. Then return:\n".
            "- verdict: pass (all criteria met), revise (gaps found), or needs_clarification (user input needed)\n".
            "- issues: array of specific gaps, each citing the rubric criterion violated (max 5)\n".
            "- confidence: 0-1 float reflecting evaluation certainty\n".
            "- For needs_clarification: max 3 focused questions.\n".
            'Cite specific evidence from the context for every issue raised.',
            $context['brief'] ?? '',
            is_array($context['qa_transcript'] ?? null) ? json_encode($context['qa_transcript'], JSON_PRETTY_PRINT) : ($context['qa_transcript'] ?? ''),
            json_encode($context['discovery_findings'] ?? [], JSON_PRETTY_PRINT),
            json_encode($context['candidate'] ?? [], JSON_PRETTY_PRINT)
        );
    }

    /**
     * Build the prompt for plan review.
     *
     * @param  array<string, mixed>  $context  Context package from ReviewerContextBuilder
     */
    private function buildPlanReviewPrompt(array $context): string
    {
        return sprintf(
            "You are a criteria-based evaluator assessing the quality of an implementation plan.\n\n".
            "## Evaluation Rubric\n\n".
            "Evaluate the plan candidate against these criteria:\n".
            "1. **Scope Coverage**: Does the plan address every requirement from the locked summary?\n".
            "2. **Summary Alignment**: Are there contradictions between the plan and the approved summary?\n".
            "3. **Task Decomposition**: Are tasks well-defined, appropriately sized, and independently verifiable?\n".
            "4. **Dependency Correctness**: Are task dependencies complete and acyclic? Are blocking paths identified?\n".
            "5. **Risk Mitigation**: Are failure modes, edge cases, and rollback strategies addressed?\n\n".
            "## Context\n\n".
            "### Feature Brief\n%s\n\n".
            "### Locked Summary\n%s\n\n".
            "### Q&A Transcript\n%s\n\n".
            "### Plan Candidate\n%s\n\n".
            "## Instructions\n\n".
            "Score the candidate against each rubric criterion. Then return:\n".
            "- verdict: pass (all criteria met) or revise (gaps found)\n".
            "- issues: array of specific gaps, each citing the rubric criterion violated (max 5)\n".
            "- confidence: 0-1 float reflecting evaluation certainty\n".
            "Do NOT return needs_clarification for plan review.\n".
            'Cite specific evidence from the context for every issue raised.',
            $context['brief'] ?? '',
            json_encode($context['locked_summary'] ?? [], JSON_PRETTY_PRINT),
            is_array($context['qa_transcript'] ?? null) ? json_encode($context['qa_transcript'], JSON_PRETTY_PRINT) : ($context['qa_transcript'] ?? ''),
            json_encode($context['candidate'] ?? [], JSON_PRETTY_PRINT)
        );
    }
}
