<?php

declare(strict_types=1);

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
 * 5. If low confidence: return clarification_required with interpretation and alternatives
 *
 * Logging policy:
 * - Application logs contain first 80 chars + SHA-256 hash of input
 * - Full input stored only in nl_parse_attempts table
 *
 * What is handled:
 * - High-confidence rule-based parsing returns immediately
 * - Low-confidence returns clarification_required with human-readable interpretation
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
     * @param  User  $user  The user making the request
     * @param  string  $input  Natural language schedule description
     * @param  string  $timezone  IANA timezone string
     * @return array{status: string, parse_attempt_id: string, result?: array}
     *
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

        // 5. Low confidence: return clarification required
        $attempt = $this->repository->create(
            $user,
            $input,
            $timezone,
            'clarification_required',
            $result
        );

        $this->logRedacted('Low-confidence parse requires clarification', $input, [
            'attempt_id' => $attempt->id,
            'confidence' => $result->confidence,
            'ambiguous' => $result->ambiguous,
        ]);

        return [
            'status' => 'clarification_required',
            'parse_attempt_id' => $attempt->id,
            'interpretation' => $result->toArray(),
            'alternatives' => $this->generateAlternatives($result),
            'message' => 'I understood this as "'.$result->explanation.'". Is this correct, or did you mean something else?',
        ];
    }

    /**
     * Generate alternative suggestions based on ambiguity in the parse result.
     *
     * @param  ParseResult  $result  The parse result to analyze
     * @return array<int, array{type: string, suggestion: string}>
     */
    private function generateAlternatives(ParseResult $result): array
    {
        $alternatives = [];

        // Check for time ambiguity (e.g., bare times without AM/PM)
        if ($result->ambiguous && str_contains(strtolower($result->explanation), 'am or pm')) {
            $alternatives[] = [
                'type' => 'time',
                'suggestion' => 'Specify AM or PM explicitly (e.g., "9:00 AM")',
            ];
        }

        // Check for frequency ambiguity (e.g., "twice a day" without specific times)
        if ($result->ambiguous && str_contains(strtolower($result->explanation), 'suggest')) {
            $alternatives[] = [
                'type' => 'frequency',
                'suggestion' => 'Clarify frequency (e.g., "daily", "weekly", "monthly")',
            ];
        }

        // Check for day ambiguity
        if ($result->ambiguous && str_contains(strtolower($result->explanation), 'confirm')) {
            $alternatives[] = [
                'type' => 'day',
                'suggestion' => 'Specify exact day of week (e.g., "every Monday")',
            ];
        }

        // Default suggestion for unknown patterns
        if ($result->ambiguous && str_contains(strtolower($result->explanation), 'unable to parse')) {
            $alternatives[] = [
                'type' => 'pattern',
                'suggestion' => 'Try a clearer format like "daily at 9:00 AM" or "every Monday at 10:00 AM"',
            ];
        }

        // If still empty but ambiguous, provide a generic suggestion
        if (empty($alternatives) && $result->ambiguous) {
            $alternatives[] = [
                'type' => 'general',
                'suggestion' => 'Please provide more specific scheduling details',
            ];
        }

        return $alternatives;
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

        if ($attempt->status === 'clarification_required') {
            // Reconstruct interpretation from stored data
            $response['interpretation'] = [
                'cron_expression' => $attempt->cron_result,
                'timezone' => $attempt->timezone,
                'confidence' => $attempt->confidence,
                'parser_path' => $attempt->parser_path,
                'active_hours' => $attempt->active_hours_result,
            ];
            $response['alternatives'] = [];
            $response['message'] = 'This schedule requires clarification. Please provide more specific details.';
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
            ? substr($input, 0, 80).'...'
            : $input;

        $context['input_preview'] = $redacted;
        $context['input_hash'] = hash('sha256', $input);

        Log::info("[NlScheduleParser] {$message}", $context);
    }
}
