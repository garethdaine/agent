<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Documentation\CoverageAuditService;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Console\Command;

class DocsCoverageCommand extends Command
{
    protected $signature = 'docs:coverage {--fail-on-missing : Exit with failure when missing coverage is detected}';

    protected $description = 'Audit documentation coverage and link/orphan integrity against required surfaces';

    public function handle(CoverageAuditService $coverageAudit): int
    {
        try {
            $report = $coverageAudit->auditRepository();
        } catch (DocumentationValidationException $exception) {
            $this->error('Docs coverage audit failed.');
            foreach ($exception->errors() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        $totals = $report['totals'];

        $this->info('Docs coverage audit completed.');
        $this->line(sprintf(
            'Coverage: %.2f%% (%d/%d surfaces)',
            $totals['coverage_percent'],
            $totals['surfaces_passed'],
            $totals['surfaces']
        ));

        foreach ($report['surfaces'] as $surface) {
            $status = $surface['passed'] ? 'PASS' : 'FAIL';
            $this->line(sprintf('[%s] %s', $status, $surface['label']));

            if (! $surface['passed']) {
                foreach ($surface['issues'] as $issue) {
                    $this->line('  - '.$issue);
                }
            }
        }

        if ($report['link_orphan_issues'] !== []) {
            $this->warn('Link/orphan check issues:');
            foreach ($report['link_orphan_issues'] as $issue) {
                $this->line('- '.$issue);
            }
        } else {
            $this->info('Link/orphan checks passed.');
        }

        $hasFailures = $totals['coverage_issues'] > 0 || $totals['link_orphan_issues'] > 0;

        if ((bool) $this->option('fail-on-missing') && $hasFailures) {
            $this->error('Coverage gate failed. Missing coverage or link/orphan issues detected.');

            return self::FAILURE;
        }

        if ($hasFailures) {
            $this->warn('Coverage issues detected (non-blocking without --fail-on-missing).');
        }

        return self::SUCCESS;
    }
}
