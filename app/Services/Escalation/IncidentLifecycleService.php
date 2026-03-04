<?php

declare(strict_types=1);

namespace App\Services\Escalation;

use App\Models\EscalationIncident;
use App\Support\Time\AgentClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IncidentLifecycleService
{
    private const STATUS_OPEN = 'open';

    private const STATUS_INVESTIGATING = 'investigating';

    private const STATUS_RESOLVED = 'resolved';

    public function __construct(
        private readonly AgentClock $clock,
    ) {}

    public function openOrRefreshIncident(
        string $workflowKey,
        string $triggerType,
        ?string $reasonCode = null,
        ?string $reason = null,
        ?string $projectionBuildId = null,
        ?array $metadata = null,
    ): EscalationIncident {
        return DB::transaction(function () use ($workflowKey, $triggerType, $reasonCode, $reason, $projectionBuildId, $metadata): EscalationIncident {
            /** @var EscalationIncident|null $existing */
            $existing = EscalationIncident::query()
                ->where('workflow_key', $workflowKey)
                ->where('trigger_type', $triggerType)
                ->whereIn('status', [self::STATUS_OPEN, self::STATUS_INVESTIGATING])
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            $now = $this->clock->nowUtc();

            if ($existing !== null) {
                $existing->forceFill([
                    'last_triggered_at' => $now,
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'metadata_json' => $metadata ?? $existing->metadata_json,
                    'updated_at' => $now,
                ])->save();

                return $existing->refresh();
            }

            try {
                /** @var EscalationIncident $incident */
                $incident = EscalationIncident::query()->create([
                    'workflow_key' => $workflowKey,
                    'trigger_type' => $triggerType,
                    'status' => self::STATUS_OPEN,
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'opened_at' => $now,
                    'investigating_at' => null,
                    'resolved_at' => null,
                    'last_triggered_at' => $now,
                    'projection_build_id' => $projectionBuildId,
                    'metadata_json' => $metadata,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return $incident;
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                /** @var EscalationIncident $incident */
                $incident = EscalationIncident::query()
                    ->where('workflow_key', $workflowKey)
                    ->where('trigger_type', $triggerType)
                    ->whereIn('status', [self::STATUS_OPEN, self::STATUS_INVESTIGATING])
                    ->lockForUpdate()
                    ->firstOrFail();

                $incident->forceFill([
                    'last_triggered_at' => $now,
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'metadata_json' => $metadata ?? $incident->metadata_json,
                    'updated_at' => $now,
                ])->save();

                return $incident->refresh();
            }
        });
    }

    public function transitionIncident(int $incidentId, string $nextStatus): EscalationIncident
    {
        return DB::transaction(function () use ($incidentId, $nextStatus): EscalationIncident {
            /** @var EscalationIncident $incident */
            $incident = EscalationIncident::query()
                ->whereKey($incidentId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = (string) $incident->status;
            if (! $this->isValidTransition($currentStatus, $nextStatus)) {
                throw new RuntimeException(sprintf(
                    'Invalid incident transition: %s -> %s',
                    $currentStatus,
                    $nextStatus,
                ));
            }

            if ($currentStatus === $nextStatus) {
                return $incident;
            }

            $now = $this->clock->nowUtc();

            $updates = [
                'status' => $nextStatus,
                'updated_at' => $now,
            ];

            if ($nextStatus === self::STATUS_INVESTIGATING && $incident->investigating_at === null) {
                $updates['investigating_at'] = $now;
            }

            if ($nextStatus === self::STATUS_RESOLVED && $incident->resolved_at === null) {
                $updates['resolved_at'] = $now;
            }

            $incident->forceFill($updates)->save();

            return $incident->refresh();
        });
    }

    private function isValidTransition(string $currentStatus, string $nextStatus): bool
    {
        return match ($currentStatus) {
            self::STATUS_OPEN => in_array($nextStatus, [self::STATUS_OPEN, self::STATUS_INVESTIGATING, self::STATUS_RESOLVED], true),
            self::STATUS_INVESTIGATING => in_array($nextStatus, [self::STATUS_INVESTIGATING, self::STATUS_RESOLVED], true),
            self::STATUS_RESOLVED => $nextStatus === self::STATUS_RESOLVED,
            default => false,
        };
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
