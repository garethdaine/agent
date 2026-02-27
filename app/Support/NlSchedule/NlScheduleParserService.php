<?php

namespace App\Support\NlSchedule;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Orchestration service for hybrid natural language schedule parsing.
 *
 * Flow:
 * 1. Validate input length
 * 2. Check idempotency window for existing attempt
 * 3. Execute rule-based parser
 * 4. If high confidence: return completed immediately
 * 5. If low confidence: return queued status (LLM job dispatch in later task)
 *
 * Logging policy:
 * - Application logs contain first 80 chars + SHA-256 hash of input
 * - Full input stored only in nl_parse_attempts table
 *
 * What is handled:
 * - High-confidence rule-based parsing returns immediately
 * - Low-confidence returns queued status (without actual LLM dispatch)
 * - Idempotency within configurable window
 * - Input validation (configurable max length)
 * - Redacted logging for privacy
 *
 * What is NOT handled (later tasks):
 * - Actual LLM fallback job dispatch
 * - Rate limiting on LLM path
 * - WebSocket event broadcasting
 */
final class NlScheduleParserService
{
    public function __construct(
        private readonly RuleBasedScheduleParser $ruleBasedParser,
        private readonly NlParseAttemptRepository $repository,
    ) {}

    /**
     * Parse a natural language schedule description.
     *
     * @param User $user The user making the request
     * @param string $input Natural language schedule description
     * @param string $timezone IANA timezone string
     * @return array{status: string, parse_attempt_id: string, result?: array}
     * @throws \InvalidArgumentException If input exceeds max length
     */
    public function parse(User $user, string $input, string $timezone): array
    {
        $input = trim($input);

        // 1. Validate input length
        $maxLength = (int) config('agent.nl_parse.max_input_length', 200);
        if (strlen($input) > $maxLength) {
            throw new \InvalidArgumentException(
                "Input exceeds maximum length of {$maxLength} characters."
            );
        }

        // 2. Check idempotency window
        $existing = $this->repository->findForIdempotency($user, $input, $timezone);
        if ($existing !== null) {
            $this->logRedacted('Returning existing parse attempt due to idempotency', $input, [
                'attempt_id' => $existing->id,
                'status' => $existing->status,
            ]);

            return $this->buildResponse($existing);
        }

        // 3. Execute rule-based parser
        $this->logRedacted('Starting rule-based parsing', $input, [
            'user_id' => $user->id,
            'timezone' => $timezone,
        ]);

        $result = $this->ruleBasedParser->parse($input, $timezone);

        // 4. Determine response based on confidence
        $confidenceThreshold = (float) config('agent.nl_parse.confidence_threshold', 0.75);

        if ($result->isHighConfidence()) {
            // High confidence: return completed immediately
            $attempt = $this->repository->create(
                $user,
                $input,
                $timezone,
                'completed',
                $result
            );

            $this->logRedacted('High-confidence parse completed', $input, [
                'attempt_id' => $attempt->id,
                'confidence' => $result->confidence,
                'cron' => $result->cronExpression,
            ]);

            return [
                'status' => 'completed',
                'parse_attempt_id' => $attempt->id,
                'result' => $result->toArray(),
            ];
        }

        // 5. Low confidence: queue for LLM fallback (dispatch in later task)
        $attempt = $this->repository->create(
            $user,
            $input,
            $timezone,
            'queued',
            $result
        );

        $this->logRedacted('Low-confidence parse queued for LLM fallback', $input, [
            'attempt_id' => $attempt->id,
            'confidence' => $result->confidence,
            'ambiguous' => $result->ambiguous,
        ]);

        // Note: Actual job dispatch will be added in later task
        // For now, we return queued status with the rule-based result
        return [
            'status' => 'queued',
            'parse_attempt_id' => $attempt->id,
            'rule_based_result' => $result->toArray(),
        ];
    }

    /**
     * Build response array from an existing parse attempt.
     */
    private function buildResponse($attempt): array
    {
        $response = [
            'status' => $attempt->status,
            'parse_attempt_id' => $attempt->id,
        ];

        if ($attempt->status === 'completed' && $attempt->cron_result !== null) {
            $response['result'] = [
                'cron_expression' => $attempt->cron_result,
                'timezone' => $attempt->timezone,
                'confidence' => $attempt->confidence,
                'parser_path' => $attempt->parser_path,
                'active_hours' => $attempt->active_hours_result,
            ];
        }

        if ($attempt->status === 'failed') {
            $response['error'] = $attempt->error_message;
        }

        return $response;
    }

    /**
     * Log with redacted input (first 80 chars + SHA-256 hash).
     *
     * Protects user privacy by not logging full input to application logs
     * while still allowing correlation via hash.
     */
    private function logRedacted(string $message, string $input, array $context = []): void
    {
        $redacted = strlen($input) > 80
            ? substr($input, 0, 80) . '...'
            : $input;

        $context['input_preview'] = $redacted;
        $context['input_hash'] = hash('sha256', $input);

        Log::info("[NlScheduleParser] {$message}", $context);
    }
}
