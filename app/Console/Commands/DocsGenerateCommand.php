<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Documentation\DocsGenerationService;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Console\Command;

class DocsGenerateCommand extends Command
{
    protected $signature = 'docs:generate
        {--source=repo : Generation source (repo)}';

    protected $description = 'Generate detailed docs artifacts and runtime snapshot sections from current code/routes';

    public function handle(DocsGenerationService $generationService): int
    {
        $source = (string) $this->option('source');

        try {
            $summary = $generationService->generate($source);
        } catch (DocumentationValidationException $exception) {
            $this->error('Docs generation failed.');
            foreach ($exception->errors() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        $this->info('Docs generation completed.');
        $this->line(sprintf(
            'Source: %s | Files written: %d | Snapshot files updated: %d | API routes indexed: %d',
            $summary['source'],
            $summary['files_written'],
            $summary['snapshot_files_updated'],
            $summary['api_routes_indexed']
        ));

        return self::SUCCESS;
    }
}
