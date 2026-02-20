<?php

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
