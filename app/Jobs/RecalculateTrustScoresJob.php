<?php

namespace App\Jobs;

use App\Models\DelegateeProfile;
use App\Support\Delegation\TrustScoreCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateTrustScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('delegation');
    }

    public function handle(): void
    {
        $calculator = app(TrustScoreCalculator::class);

        DelegateeProfile::where('is_active', true)
            ->chunk(100, function ($profiles) use ($calculator): void {
                foreach ($profiles as $profile) {
                    $trustScore = $calculator->calculate($profile->runner_type);

                    $profile->update([
                        'trust_score' => $trustScore->score,
                        'trust_updated_at' => now(),
                    ]);
                }
            });
    }
}
