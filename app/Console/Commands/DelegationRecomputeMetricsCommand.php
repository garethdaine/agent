<?php

namespace App\Console\Commands;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Delegation\DelegateeMetricsRecomputer;
use Illuminate\Console\Command;

/**
 * Artisan command to recompute delegatee metrics.
 *
 * This command is scheduled to run every 15 minutes to:
 * - Update 24-hour sliding window metrics
 * - Update 7-day sliding window metrics
 *
 * Metrics include success rates, attempt counts, and average durations
 * which are used by DelegateeAssigner for ranking candidates.
 */
class DelegationRecomputeMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:recompute-metrics
        {--profile= : Recompute metrics for a specific profile ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute sliding window metrics for delegatee profiles';

    /**
     * Execute the console command.
     */
    public function handle(DelegateeMetricsRecomputer $recomputer, FeatureFlagManager $featureFlags): int
    {
        if (! $featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            $this->info('Delegation feature is disabled. Skipping metrics recomputation.');

            return self::SUCCESS;
        }

        $profileId = $this->option('profile');

        if ($profileId !== null) {
            $this->info("Recomputing metrics for profile ID: {$profileId}");

            $profile = \App\Models\DelegateeProfile::find($profileId);
            if ($profile === null) {
                $this->error("Profile not found: {$profileId}");

                return self::FAILURE;
            }

            $recomputer->recompute($profile);
            $this->info('Metrics recomputation complete for single profile.');

            return self::SUCCESS;
        }

        $this->info('Recomputing metrics for all active delegatee profiles...');

        $recomputer->recomputeAll();

        $this->info('Metrics recomputation complete.');

        return self::SUCCESS;
    }
}
