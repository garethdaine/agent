<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Documentation\DocsSyncService;
use App\Support\Documentation\DocsTelemetryService;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Console\Command;
use Throwable;

class DocsSyncCommand extends Command
{
    protected $signature = 'docs:sync
        {--mode=commit : Sync mode (commit|deploy)}
        {--source=repo : Sync source (repo)}';

    protected $description = 'Parse, validate, normalize, and upsert repository docs content';

    public function handle(DocsSyncService $syncService, DocsTelemetryService $telemetry): int
    {
        $mode = strtolower(trim((string) $this->option('mode')));
        $source = strtolower(trim((string) $this->option('source')));

        /** @var array<int, string> $allowedModes */
        $allowedModes = config('documentation.sync.allowed_modes', ['commit', 'deploy']);
        /** @var array<int, string> $allowedSources */
        $allowedSources = config('documentation.sync.allowed_sources', ['repo']);

        if (! in_array($mode, $allowedModes, true)) {
            $this->error(sprintf(
                "Invalid --mode value '%s'. Allowed values: %s",
                $mode,
                implode(', ', $allowedModes)
            ));

            return self::FAILURE;
        }

        if (! in_array($source, $allowedSources, true)) {
            $this->error(sprintf(
                "Invalid --source value '%s'. Allowed values: %s",
                $source,
                implode(', ', $allowedSources)
            ));

            return self::FAILURE;
        }

        try {
            $summary = $syncService->sync($mode, $source);
        } catch (DocumentationValidationException $exception) {
            $telemetry->recordSyncOutcome(
                mode: $mode,
                source: $source,
                success: false,
                summary: [
                    'entries_count' => 0,
                    'fragments_count' => 0,
                    'links_count' => 0,
                ],
                errorCode: 'DOCS_VALIDATION_FAILED',
                errorMessage: $exception->getMessage()
            );

            $this->error('Docs sync failed.');
            foreach ($exception->errors() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $telemetry->recordSyncOutcome(
                mode: $mode,
                source: $source,
                success: false,
                summary: [
                    'entries_count' => 0,
                    'fragments_count' => 0,
                    'links_count' => 0,
                ],
                errorCode: 'DOCS_SYNC_FAILED',
                errorMessage: $exception->getMessage()
            );

            $this->error('Docs sync failed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $telemetry->recordSyncOutcome(
            mode: (string) $summary['mode'],
            source: (string) $summary['source'],
            success: true,
            summary: [
                'entries_count' => (int) $summary['entries_count'],
                'fragments_count' => (int) $summary['fragments_count'],
                'links_count' => (int) $summary['links_count'],
            ]
        );

        $this->info('Docs sync completed.');
        $this->line(sprintf(
            'Mode: %s | Source: %s | Entries: %d | Fragments: %d | Links: %d',
            $summary['mode'],
            $summary['source'],
            $summary['entries_count'],
            $summary['fragments_count'],
            $summary['links_count']
        ));
        $this->line('Manifest: '.$summary['manifest_path']);

        return self::SUCCESS;
    }
}
