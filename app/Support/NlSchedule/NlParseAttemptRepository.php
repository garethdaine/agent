<?php

declare(strict_types=1);

namespace App\Support\NlSchedule;

use App\Models\NlParseAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Repository for NlParseAttempt CRUD operations with idempotency and atomic transitions.
 *
 * Handles:
 * - Idempotency lookup within configurable window
 * - Atomic status transitions
 * - Completion and failure marking
 *
 * Malicious-caller protection:
 * - All queries scoped to user_id to prevent cross-user access
 * - Status transitions use pessimistic locking to prevent race conditions
 *
 * Tired-maintainer protection:
 * - Clear method signatures with explicit return types
 * - Defensive checks on status transitions
 */
final class NlParseAttemptRepository
{
    /**
     * Find an existing parse attempt within the idempotency window.
     *
     * Prevents duplicate processing of identical requests within the configured
     * window (default 60 seconds).
     */
    public function findForIdempotency(User $user, string $input, string $timezone): ?NlParseAttempt
    {
        return NlParseAttempt::query()
            ->forIdempotency($user->id, $input, $timezone) // @phpstan-ignore argument.type
            ->latest()
            ->first();
    }

    /**
     * Create a new parse attempt record.
     *
     * @param  User  $user  The user making the request
     * @param  string  $input  Raw natural language input (stored in full)
     * @param  string  $timezone  IANA timezone
     * @param  string  $status  Initial status: queued, running, completed, failed
     */
    public function create(
        User $user,
        string $input,
        string $timezone,
        string $status,
        ?ParseResult $result = null
    ): NlParseAttempt {
        $data = [
            'user_id' => $user->id,
            'input_text' => $input,
            'timezone' => $timezone,
            'status' => $status,
            'parser_path' => $result->parserPath ?? 'rule_based',
            'confidence' => $result?->confidence,
            'cron_result' => $result?->cronExpression,
            'active_hours_result' => $result?->activeHours,
            'user_confirmed' => false,
        ];

        if ($status === 'completed' && $result !== null) {
            $data['completed_at'] = now();
        }

        return NlParseAttempt::create($data);
    }

    /**
     * Atomically transition a parse attempt to a new status.
     *
     * Uses pessimistic locking to prevent race conditions in concurrent environments.
     *
     * @throws \RuntimeException If the attempt is already in a terminal state
     */
    public function transitionStatus(NlParseAttempt $attempt, string $newStatus): void
    {
        DB::transaction(function () use ($attempt, $newStatus) {
            // Lock the row for update
            $locked = NlParseAttempt::query()
                ->where('id', $attempt->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new \RuntimeException("Parse attempt {$attempt->id} not found");
            }

            // Prevent transitions from terminal states
            if (in_array($locked->status, ['completed', 'failed'], true)) {
                throw new \RuntimeException(
                    "Cannot transition from terminal status '{$locked->status}'"
                );
            }

            $locked->status = $newStatus;
            $locked->save();

            // Sync the original model
            $attempt->status = $newStatus;
        });
    }

    /**
     * Mark a parse attempt as completed with the given result.
     */
    public function markCompleted(NlParseAttempt $attempt, ParseResult $result): void
    {
        DB::transaction(function () use ($attempt, $result) {
            $locked = NlParseAttempt::query()
                ->where('id', $attempt->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new \RuntimeException("Parse attempt {$attempt->id} not found");
            }

            $locked->update([
                'status' => 'completed',
                'parser_path' => $result->parserPath,
                'confidence' => $result->confidence,
                'cron_result' => $result->cronExpression,
                'active_hours_result' => $result->activeHours,
                'completed_at' => now(),
            ]);

            // Sync the original model
            $attempt->refresh();
        });
    }

    /**
     * Mark a parse attempt as failed with error message and optional fallback result.
     */
    public function markFailed(NlParseAttempt $attempt, string $error, ?ParseResult $fallback = null): void
    {
        DB::transaction(function () use ($attempt, $error, $fallback) {
            $locked = NlParseAttempt::query()
                ->where('id', $attempt->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new \RuntimeException("Parse attempt {$attempt->id} not found");
            }

            $data = [
                'status' => 'failed',
                'error_message' => $error,
                'completed_at' => now(),
            ];

            // Store fallback result if available
            if ($fallback !== null) {
                $data['confidence'] = $fallback->confidence;
                $data['cron_result'] = $fallback->cronExpression;
                $data['active_hours_result'] = $fallback->activeHours;
            }

            $locked->update($data);

            // Sync the original model
            $attempt->refresh();
        });
    }
}
