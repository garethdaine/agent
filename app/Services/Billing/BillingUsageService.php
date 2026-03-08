<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class BillingUsageService
{
    public function reportRunCompleted(User $user, int $runs = 1): void
    {
        if (! config('billing.enabled', false)) {
            return;
        }

        if (! $user->hasStripeId()) {
            return;
        }

        $meter = config('billing.meters.runs', 'agent_runs');
        if ($meter === '' || $meter === 'price_monthly') {
            return;
        }

        try {
            $user->reportMeterEvent($meter, $runs);
        } catch (\Throwable $e) {
            Log::warning('BillingUsageService: failed to report run usage', [
                'user_id' => $user->id,
                'meter' => $meter,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function reportTokenUsage(User $user, int $tokens): void
    {
        if (! config('billing.enabled', false) || $tokens <= 0) {
            return;
        }

        if (! $user->hasStripeId()) {
            return;
        }

        $meter = config('billing.meters.tokens', 'agent_tokens');
        if ($meter === '') {
            return;
        }

        try {
            $user->reportMeterEvent($meter, $tokens);
        } catch (\Throwable $e) {
            Log::warning('BillingUsageService: failed to report token usage', [
                'user_id' => $user->id,
                'meter' => $meter,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
