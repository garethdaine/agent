<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Documentation\OpenApiArtifactIngestService;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Console\Command;

class DocsOpenApiIngestCommand extends Command
{
    protected $signature = 'docs:openapi:ingest
        {--path= : Override OpenAPI artifact path (defaults to documentation.openapi.artifact_path)}';

    protected $description = 'Ingest Scramble OpenAPI artifact metadata into ApiDocArtifact records';

    public function handle(OpenApiArtifactIngestService $ingestService): int
    {
        try {
            $summary = $ingestService->ingest($this->option('path'));
        } catch (DocumentationValidationException $exception) {
            $this->error('OpenAPI ingest failed.');
            foreach ($exception->errors() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        $this->info('OpenAPI ingest completed.');
        $this->line(sprintf(
            'Artifact: %s | Operations: %d | Version: %s',
            $summary['artifact_path'],
            $summary['operations_count'],
            $summary['spec_version'] ?? 'n/a'
        ));
        $this->line('Checksum: '.$summary['spec_checksum']);

        return self::SUCCESS;
    }
}
