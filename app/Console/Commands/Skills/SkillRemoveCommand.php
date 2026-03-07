<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Models\AgentSkill;
use App\Services\Skills\SkillInstaller;
use Illuminate\Console\Command;

class SkillRemoveCommand extends Command
{
    protected $signature = 'skill:remove
        {name : Skill name to remove}
        {--team= : Team ID}
        {--force : Skip confirmation}';

    protected $description = 'Remove an installed skill';

    public function handle(SkillInstaller $installer): int
    {
        $name = $this->argument('name');
        $teamId = $this->option('team') ? (int) $this->option('team') : null;

        $query = AgentSkill::where('name', $name);

        if ($teamId !== null) {
            $query->where(function ($q) use ($teamId) {
                $q->forTeam($teamId)->orWhere(fn ($q2) => $q2->global());
            });
        }

        $skill = $query->first();

        if ($skill === null) {
            $this->error("Skill '{$name}' not found.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Are you sure you want to remove skill '{$name}'?")) {
            $this->info('Removal cancelled.');

            return self::SUCCESS;
        }

        $installer->remove($skill->id);

        $this->info("Skill '{$name}' removed successfully.");

        return self::SUCCESS;
    }
}
