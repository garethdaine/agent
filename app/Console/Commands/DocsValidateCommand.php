<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Documentation\CoverageAuditService;
use App\Support\Documentation\Ingestion\DocsContractValidator;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Console\Command;

class DocsValidateCommand extends Command
{
    protected $signature = 'docs:validate {--fail-fast : Stop on first validation failure}';

    protected $description = 'Validate docs markdown front matter and tooltip YAML contract';

    public function handle(DocsContractValidator $validator, CoverageAuditService $coverageAudit): int
    {
        try {
            $summary = $validator->validateRepository((bool) $this->option('fail-fast'));
        } catch (DocumentationValidationException $exception) {
            $this->error('Docs validation failed.');
            foreach ($exception->errors() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        try {
            $report = $coverageAudit->auditRepository();
        } catch (DocumentationValidationException $exception) {
            $this->error('Docs validation failed.');
            foreach ($exception->errors() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        if ($report['link_orphan_issues'] !== []) {
            $this->error('Docs validation failed.');
            foreach ($report['link_orphan_issues'] as $issue) {
                $this->line('- '.$issue);
            }

            return self::FAILURE;
        }

        $this->info('Docs validation passed.');
        $this->line(sprintf(
            'Validated markdown files: %d, tooltip files: %d, tooltip fragments: %d',
            $summary['markdown_files'],
            $summary['tooltip_files'],
            $summary['tooltip_fragments']
        ));
        $this->line('Link/orphan checks: passed.');

        return self::SUCCESS;
    }
}
