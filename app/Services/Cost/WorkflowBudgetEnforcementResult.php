<?php

declare(strict_types=1);

namespace App\Services\Cost;

final class WorkflowBudgetEnforcementResult
{
    public function __construct(
        public readonly string $thresholdStatus,
        public readonly bool $enforcementApplied,
        public readonly float $canonicalRunCostUsd,
        public readonly ?float $providerBilledRunCostUsd,
        public readonly float $monthlyCanonicalSpendUsd,
        public readonly float $monthlyProviderBilledSpendUsd,
        public readonly ?float $utilizationPercent,
        public readonly ?float $monthlyBudgetUsd,
        public readonly ?float $warningThresholdPercent,
        public readonly ?float $enforcementThresholdPercent,
        public readonly string $pinnedRateCardVersion,
        public readonly bool $rollupCreated,
    ) {}
}
