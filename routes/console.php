<?php

use App\Jobs\Org\OrgDispatchDueRitualsJob;
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

Schedule::command('repo-analysis:prune-artifacts')
    ->dailyAt('03:40')
    ->withoutOverlapping();

// Memory consolidation (every 2 hours when enabled)
Schedule::command('memory:consolidate')
    ->cron('0 */2 * * *')
    ->withoutOverlapping()
    ->when(fn () => config('memory.enabled'));

// Memory pruning (daily at 03:30 with --force when enabled)
Schedule::command('memory:prune --force')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->when(fn () => config('memory.enabled'));

// Org ritual scheduling (every minute when org layer enabled)
Schedule::job(new OrgDispatchDueRitualsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => config('agent.org.enabled'));
