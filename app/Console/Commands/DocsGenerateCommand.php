<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Documentation\DocsGenerationService;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocsGenerateCommand extends Command
{
    protected $signature = 'docs:generate
        {--source=repo : Generation source (repo)}
        {--export-openapi : Also export the Scramble OpenAPI spec to docs/openapi/}';

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

        if ($this->option('export-openapi') && config('documentation.openapi.scramble_enabled', true)) {
            $this->exportScrambleSpec();
        }

        return self::SUCCESS;
    }

    private function exportScrambleSpec(): void
    {
        try {
            $specJson = file_get_contents(url('/docs/api.json'));

            if ($specJson === false) {
                $this->warn('Could not fetch Scramble OpenAPI spec from /docs/api.json (is the server running?).');

                return;
            }

            $outputDir = base_path('docs/openapi');
            File::ensureDirectoryExists($outputDir);
            File::put("{$outputDir}/agent-api-v1.json", $specJson);
            $this->info('Exported Scramble OpenAPI spec to docs/openapi/agent-api-v1.json');
        } catch (\Throwable $e) {
            $this->warn('Scramble OpenAPI export skipped: '.$e->getMessage());
        }
    }
}
