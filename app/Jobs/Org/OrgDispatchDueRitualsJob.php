<?php

namespace App\Jobs\Org;

use App\Models\OrgRitualTemplate;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job that dispatches OrgExecuteRitualJob for all rituals that are due.
 *
 * This job runs on the scheduler (every minute) and checks each active,
 * non-paused ritual template to see if its cron expression indicates
 * it should run now. For each due ritual, it dispatches an OrgExecuteRitualJob.
 */
class OrgDispatchDueRitualsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Skip if org layer is disabled
        if (! config('agent.org.enabled', false)) {
            return;
        }

        $dueTemplates = $this->getDueTemplates();

        foreach ($dueTemplates as $template) {
            OrgExecuteRitualJob::dispatch($template)->onQueue('org-rituals');
        }
    }

    /**
     * Get all ritual templates that are due to run now.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrgRitualTemplate>
     */
    protected function getDueTemplates(): \Illuminate\Database\Eloquent\Collection
    {
        $templates = OrgRitualTemplate::query()
            ->scheduled() // active and not paused
            ->get();

        return $templates->filter(function (OrgRitualTemplate $template) {
            return $this->isDue($template);
        });
    }

    /**
     * Check if a ritual template is due to run based on its cron expression.
     */
    protected function isDue(OrgRitualTemplate $template): bool
    {
        $cronExpression = $template->cron_expression;
        $timezone = $template->timezone ?? 'UTC';

        try {
            $cron = new CronExpression($cronExpression);
            $tz = new DateTimeZone($timezone);
            $now = new \DateTimeImmutable('now', $tz);

            return $cron->isDue($now);
        } catch (\Throwable) {
            // Invalid cron expression or timezone - skip this template
            return false;
        }
    }
}
