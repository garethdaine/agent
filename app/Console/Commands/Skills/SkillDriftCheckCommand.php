<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Models\AgentSkill;
use App\Services\Skills\SkillDriftDetector;
use Illuminate\Console\Command;

class SkillDriftCheckCommand extends Command
{
    protected $signature = 'skill:drift-check
        {--team= : Team ID}';

    protected $description = 'Check installed skills for file drift';

    public function handle(SkillDriftDetector $detector): int
    {
        $teamId = $this->option('team') ? (int) $this->option('team') : null;

        $results = $detector->checkAll($teamId);

        if ($results->isEmpty()) {
            $this->info('No active skills to check.');

            return self::SUCCESS;
        }

        $driftedResults = $results->filter(fn ($r) => $r->hasDrift);

        if ($driftedResults->isEmpty()) {
            $this->info('No drift detected. All skills are clean.');

            return self::SUCCESS;
        }

        foreach ($driftedResults as $result) {
            AgentSkill::where('id', $result->skillId)
                ->update(['status' => AgentSkill::STATUS_PENDING_REVIEW]);

            $this->warn("Drift detected in '{$result->skillName}':");

            foreach ($result->changedFiles as $file) {
                $this->line("  Modified: {$file}");
            }
            foreach ($result->addedFiles as $file) {
                $this->line("  Added: {$file}");
            }
            foreach ($result->missingFiles as $file) {
                $this->line("  Missing: {$file}");
            }
        }

        $rows = $results->map(fn ($r) => [
            $r->skillName,
            $r->hasDrift ? 'DRIFT' : 'CLEAN',
            implode(', ', $r->changedFiles),
            implode(', ', $r->addedFiles),
            implode(', ', $r->missingFiles),
        ])->toArray();

        $this->table(
            ['Skill', 'Status', 'Changed', 'Added', 'Missing'],
            $rows,
        );

        $this->warn(sprintf('%d skill(s) with drift detected.', $driftedResults->count()));

        return self::SUCCESS;
    }
}
