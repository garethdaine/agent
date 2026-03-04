<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\ReliabilityReasonCode;
use App\Enums\ReliabilityRunClassification;
use App\Models\RunClassification;
use App\Support\Time\AgentClock;

final class AssistedSlaExpiryReclassifier
{
    public function __construct(
        private readonly AgentClock $clock,
    ) {}

    public function reclassifyExpired(int $chunkSize = 200): int
    {
        $expiredCount = 0;
        $now = $this->clock->nowUtc();

        RunClassification::query()
            ->where('classification', ReliabilityRunClassification::ASSISTED->value)
            ->whereNull('verification_completed_at')
            ->whereNotNull('assisted_sla_expires_at')
            ->where('assisted_sla_expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$expiredCount, $now): void {
                foreach ($rows as $row) {
                    $row->forceFill([
                        'classification' => ReliabilityRunClassification::FAILED->value,
                        'weight' => ReliabilityRunClassification::FAILED->weight(),
                        'classification_reason_code' => ReliabilityReasonCode::ASSISTED_SLA_EXPIRED->value,
                        'reclassified_at' => $now,
                        'updated_at' => $now,
                    ])->save();

                    $expiredCount++;
                }
            });

        return $expiredCount;
    }
}
