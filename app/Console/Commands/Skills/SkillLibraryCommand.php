<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Services\Skills\SkillLibrary;
use Illuminate\Console\Command;

class SkillLibraryCommand extends Command
{
    protected $signature = 'skill:library
        {--category= : Filter by category}
        {--industry= : Filter by industry}';

    protected $description = 'List available skills from the skill library';

    public function handle(SkillLibrary $library): int
    {
        $filters = [];

        if ($this->option('category')) {
            $filters['category'] = $this->option('category');
        }

        if ($this->option('industry')) {
            $filters['industry'] = $this->option('industry');
        }

        $entries = $library->browse($filters);

        if ($entries->isEmpty()) {
            $this->info('No skills found in the library.');

            return self::SUCCESS;
        }

        $rows = $entries->map(fn ($entry) => [
            $entry->slug,
            $entry->name,
            $entry->category,
            $entry->riskLevel,
            $entry->version,
        ])->toArray();

        $this->table(
            ['Slug', 'Name', 'Category', 'Risk Level', 'Version'],
            $rows,
        );

        return self::SUCCESS;
    }
}
