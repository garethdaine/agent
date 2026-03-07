<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Models\AgentSkill;
use Illuminate\Console\Command;

class SkillListCommand extends Command
{
    protected $signature = 'skill:list
        {--team= : Team ID}
        {--status= : Filter by status}';

    protected $description = 'List installed skills';

    public function handle(): int
    {
        $teamId = $this->option('team') ? (int) $this->option('team') : null;
        $status = $this->option('status');

        $query = AgentSkill::query();

        if ($teamId !== null) {
            $query->where(function ($q) use ($teamId) {
                $q->forTeam($teamId)->orWhere(fn ($q2) => $q2->global());
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $skills = $query->orderBy('name')->get();

        if ($skills->isEmpty()) {
            $this->info('No skills installed.');

            return self::SUCCESS;
        }

        $rows = $skills->map(fn (AgentSkill $skill) => [
            $skill->name,
            $skill->version,
            $skill->status,
            $skill->risk_level,
            $skill->invocation_count,
            $skill->last_invoked_at?->toDateTimeString() ?? 'Never',
        ])->toArray();

        $this->table(
            ['Name', 'Version', 'Status', 'Risk Level', 'Invocations', 'Last Invoked'],
            $rows,
        );

        return self::SUCCESS;
    }
}
