<?php

namespace App\Jobs\Org;

use App\Models\OrgRitualTemplate;
use App\Support\Agent\FeatureFlagManager;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OrgDispatchDueRitualsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('org-rituals');
    }

    public function handle(): void
    {
        // Skip if org layer is disabled
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::ORG_ENABLED)) {
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
