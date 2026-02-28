<?php

namespace App\Messenger\Reliability;

use App\Jobs\Messenger\ProcessChatIntent;
use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manages the dead-letter queue for failed messenger messages.
 *
 * Messages that exceed retry duration are moved to the DLQ where they can be
 * inspected, manually retried, or discarded. Provides methods for moving
 * messages to the DLQ, retrying individual or bulk messages, and querying
 * dead letters by connector.
 *
 * Conditions for correctness:
 * - Original payload must contain message_id, session_id, and user_id for retry to succeed
 * - Connector account must exist and be active for retry to succeed
 * - Retry operations are atomic: success deletes the record, failure updates error history
 *
 * Known limitations:
 * - Retry does not validate connector health before dispatching
 * - No automatic retry scheduling; all retries are manual
 * - Error history grows unbounded (consider pruning in long-running systems)
 */
class DeadLetterManager
{
    /**
     * Move a failed message to the dead-letter queue.
     *
     * Creates a new dead letter record capturing the original payload,
     * error message, and number of attempts made before failure.
     */
    public function moveToDeadLetter(
        ConnectorAccount $connector,
        array $payload,
        string $error,
        int $attempts
    ): MessengerDeadLetter {
        Log::info('Moving message to dead-letter queue', [
            'connector_id' => $connector->id,
            'connector_name' => $connector->name,
            'attempts' => $attempts,
            'error' => $error,
        ]);

        return MessengerDeadLetter::create([
            'connector_account_id' => $connector->id,
            'original_payload' => $payload,
            'error_message' => $error,
            'error_history' => [
                [
                    'error' => $error,
                    'at' => now()->toIso8601String(),
                ],
            ],
            'attempts' => $attempts,
            'failed_at' => now(),
        ]);
    }

    /**
     * Retry a dead letter message by dispatching a new job.
     *
     * On success, deletes the dead letter record.
     * On failure, updates error_history and increments attempts.
     *
     * @param  int  $deadLetterId  The ID of the dead letter to retry
     * @return bool True if retry job was successfully dispatched, false otherwise
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If dead letter not found
     */
    public function retry(int $deadLetterId): bool
    {
        $deadLetter = MessengerDeadLetter::findOrFail($deadLetterId);

        try {
            $this->validatePayloadForRetry($deadLetter->original_payload);

            ProcessChatIntent::dispatch(
                $deadLetter->original_payload['message_id'],
                $deadLetter->original_payload['session_id'],
                $deadLetter->original_payload['user_id']
            );

            Log::info('Dead letter retry successful, deleting record', [
                'dead_letter_id' => $deadLetterId,
            ]);

            $deadLetter->delete();

            return true;
        } catch (Throwable $e) {
            Log::warning('Dead letter retry failed', [
                'dead_letter_id' => $deadLetterId,
                'error' => $e->getMessage(),
            ]);

            $deadLetter->addErrorToHistory($e->getMessage());
            $deadLetter->update([
                'error_message' => $e->getMessage(),
                'error_history' => $deadLetter->error_history,
                'attempts' => $deadLetter->attempts + 1,
                'retried_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Retry multiple dead letter messages in bulk.
     *
     * Returns an array of results indicating success/failure for each ID.
     *
     * @param  array<int>  $ids  Array of dead letter IDs to retry
     * @return array<array{id: int, success: bool}> Results for each retry attempt
     */
    public function retryBulk(array $ids): array
    {
        return collect($ids)->map(fn ($id) => [
            'id' => $id,
            'success' => $this->retry($id),
        ])->all();
    }

    /**
     * Get dead letters for a specific connector.
     *
     * @param  string  $connectorAccountId  The connector account ID
     * @return Collection<int, MessengerDeadLetter>
     */
    public function getByConnector(string $connectorAccountId): Collection
    {
        return MessengerDeadLetter::query()
            ->forConnector($connectorAccountId)
            ->recentFirst()
            ->get();
    }

    /**
     * Delete a dead letter record.
     *
     * @param  int  $deadLetterId  The ID of the dead letter to delete
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If dead letter not found
     */
    public function delete(int $deadLetterId): void
    {
        $deadLetter = MessengerDeadLetter::findOrFail($deadLetterId);
        $deadLetter->delete();

        Log::info('Dead letter deleted', [
            'dead_letter_id' => $deadLetterId,
        ]);
    }

    /**
     * Get total count of dead letters.
     */
    public function getCount(): int
    {
        return MessengerDeadLetter::count();
    }

    /**
     * Get count of dead letters for a specific connector.
     */
    public function getCountForConnector(string $connectorAccountId): int
    {
        return MessengerDeadLetter::query()
            ->forConnector($connectorAccountId)
            ->count();
    }

    /**
     * Validate that the payload contains required fields for retry.
     *
     * @throws \InvalidArgumentException If required fields are missing
     */
    private function validatePayloadForRetry(array $payload): void
    {
        $required = ['message_id', 'session_id', 'user_id'];
        $missing = [];

        foreach ($required as $field) {
            if (! isset($payload[$field]) || $payload[$field] === '') {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required fields for retry: '.implode(', ', $missing)
            );
        }
    }
}
