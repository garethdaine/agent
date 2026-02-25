<?php

namespace App\Console\Commands;

use App\Support\Delegation\DelegationReconciler;
use Illuminate\Console\Command;

/**
 * Artisan command to run the delegation reconciler.
 *
 * This command is scheduled to run every 2 minutes to:
 * - Handle expired human approval verification results
 * - Retry blocked tasks awaiting delegatee assignment
 * - Detect stuck running graphs
 * - Enforce graceful cancellation timeouts
 */
class DelegationReconcileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delegation:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile delegation graphs: handle expired approvals, retry blocked tasks, and enforce cancellation timeouts';

    /**
     * Execute the console command.
     */
    public function handle(DelegationReconciler $reconciler): int
    {
        if (! config('delegation.enabled', false)) {
            $this->info('Delegation feature is disabled. Skipping reconciliation.');

            return self::SUCCESS;
        }

        $this->info('Running delegation reconciler...');

        $reconciler->reconcile();

        $this->info('Delegation reconciliation complete.');

        return self::SUCCESS;
    }
}
