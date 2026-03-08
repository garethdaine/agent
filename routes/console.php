<?php

use App\Jobs\Memory\MemoryConsolidationJob;
use App\Jobs\Memory\MemoryPruneJob;
use App\Jobs\Org\OrgDispatchDueRitualsJob;
use App\Jobs\Org\OrgEscalationTimeoutJob;
use App\Jobs\RecalculateTrustScoresJob;
use App\Jobs\Security\SecurityMaintenanceJob;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agent:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('agent:prune --runs --events')
    ->dailyAt('03:10');

Schedule::command('agent:prune --jobs')
    ->dailyAt('03:20');

Schedule::command('agent:backup-database')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('agent:reliability-assisted-sla')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('messenger:prune --deduplication')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('messenger:prune --sessions --messages --attachments')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('delegation:reconcile')
    ->everyTwoMinutes()
    ->withoutOverlapping();

Schedule::command('delegation:recompute-metrics')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('nl-parse:cleanup')
    ->dailyAt('03:00');

Schedule::command('code-analysis:prune-artifacts')
    ->dailyAt('03:40')
    ->withoutOverlapping();

// Memory consolidation (every 2 hours when enabled)
Schedule::job(new MemoryConsolidationJob)
    ->cron('0 */2 * * *')
    ->withoutOverlapping()
    ->when(fn () => app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED));

// Memory pruning (daily at 03:30 when enabled)
Schedule::job(new MemoryPruneJob(dryRun: false))
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->when(fn () => app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED));

// Persist messenger metrics to database (hourly)
Schedule::call(fn () => app(\App\Support\Messenger\MetricsCollector::class)->persistAndReset())
    ->hourly()
    ->name('messenger:persist-metrics')
    ->withoutOverlapping();

// Expire stale runtime approval requests (every 5 minutes)
Schedule::call(fn () => app(\App\Services\Runtime\ApprovalGate::class)->expireStaleApprovals())
    ->everyFiveMinutes()
    ->name('runtime:expire-stale-approvals')
    ->withoutOverlapping();

// Recalculate delegatee trust scores (hourly)
Schedule::job(new RecalculateTrustScoresJob)
    ->hourly()
    ->withoutOverlapping();

// Org ritual scheduling (every minute when org layer enabled)
Schedule::job(new OrgDispatchDueRitualsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => app(FeatureFlagManager::class)->enabled(FeatureFlagManager::ORG_ENABLED));

// Org escalation timeout check (every minute when org layer enabled)
Schedule::job(new OrgEscalationTimeoutJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => app(FeatureFlagManager::class)->enabled(FeatureFlagManager::ORG_ENABLED));

// Skill drift detection (daily when skills enabled)
Schedule::command('skill:drift-check')
    ->daily()
    ->withoutOverlapping()
    ->when(fn () => app(FeatureFlagManager::class)->enabled(FeatureFlagManager::SKILLS_ENABLED));

// Security maintenance: purge expired security events and file provenance records
Schedule::job(new SecurityMaintenanceJob)
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->name('security:maintenance');

// Refresh expiring connector credentials (every minute when credential refresh enabled)
Schedule::job(new \App\Jobs\Connectors\RefreshConnectorCredentialsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => config('connectors.credential_refresh'));

// Prune old connector telemetry (daily at 03:00 when connectors enabled)
Schedule::command('connector:prune-telemetry')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->when(fn () => config('connectors.enabled'));

// Projection auto-maintenance: activate parity-passed builds and trigger rebuilds when stale
Schedule::call(fn () => app(\App\Services\Telemetry\ProjectionBuildManager::class)->autoMaintain(
    app(\App\Services\Telemetry\ActiveBuildFreshnessService::class),
    app(\App\Services\Telemetry\ActiveProjectionBuildStateService::class),
))
    ->everyFiveMinutes()
    ->name('telemetry:projection-auto-maintain')
    ->withoutOverlapping();
