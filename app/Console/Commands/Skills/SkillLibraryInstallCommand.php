<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Services\Skills\SkillInstaller;
use App\Services\Skills\SkillLibrary;
use Illuminate\Console\Command;

class SkillLibraryInstallCommand extends Command
{
    protected $signature = 'skill:library:install
        {slug : Library skill slug to install}
        {--team= : Team ID}';

    protected $description = 'Install a skill from the skill library';

    public function handle(SkillLibrary $library, SkillInstaller $installer): int
    {
        $slug = $this->argument('slug');
        $teamId = (int) ($this->option('team') ?? 0);
        $userId = null;

        $entry = $library->find($slug);

        if ($entry === null) {
            $this->error("Skill '{$slug}' not found in the library.");

            return self::FAILURE;
        }

        $manifestDir = dirname(config('agent.skills.library_manifest_path'));
        $filePath = $manifestDir.'/'.$entry->filePath;

        if (! file_exists($filePath)) {
            $this->error("Skill archive not found at: {$filePath}");

            return self::FAILURE;
        }

        $result = $installer->installFromLibrary($slug, $filePath, $teamId, $userId);

        if (! $result->success) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->warn("Warning: {$warning}");
        }

        $this->info("Skill '{$entry->name}' v{$entry->version} installed successfully.");

        return self::SUCCESS;
    }
}
