<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Services\Skills\SkillParser;
use App\Services\Skills\SkillValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SkillValidateCommand extends Command
{
    protected $signature = 'skill:validate {path : Path to skill directory or .skill file}';

    protected $description = 'Validate a skill without installing it';

    public function handle(SkillParser $parser, SkillValidator $validator): int
    {
        $path = $this->argument('path');

        $skillMdPath = is_dir($path) ? rtrim($path, '/').'/SKILL.md' : $path;

        if (! File::exists($skillMdPath)) {
            $this->error("SKILL.md not found at: {$skillMdPath}");

            return self::FAILURE;
        }

        $content = File::get($skillMdPath);
        $parsed = $parser->parse($content);

        if ($parsed->hasErrors()) {
            $this->error('Parse errors:');
            foreach ($parsed->errors as $error) {
                $this->error("  - {$error}");
            }

            return self::FAILURE;
        }

        $extractedPath = is_dir($path) ? $path : dirname($path);
        $result = $validator->validate($extractedPath, $parsed, 'validate', 0);

        $rows = [];
        foreach ($result->stages as $name => $stage) {
            $rows[] = [
                $name,
                $stage->passed ? 'PASS' : 'FAIL',
                $stage->checks,
                count($stage->failures),
                count($stage->warnings),
            ];
        }

        $this->table(
            ['Stage', 'Result', 'Checks', 'Failures', 'Warnings'],
            $rows,
        );

        $this->info(sprintf('Validation Risk Score: %.3f', $result->riskScore));
        $this->info(sprintf('Overall: %s', $result->overallPass ? 'PASS' : 'FAIL'));

        foreach ($result->warnings as $warning) {
            $this->warn("Warning: {$warning}");
        }

        foreach ($result->blockingErrors as $error) {
            $this->error("Error: {$error['message']}");
        }

        return $result->overallPass ? self::SUCCESS : self::FAILURE;
    }
}
