<?php

namespace App\Console\Commands;

use App\Support\Agent\SecurityAuditService;
use Illuminate\Console\Command;

class AgentSecurityAuditCommand extends Command
{
    protected $signature = 'agent:security-audit
                            {--json : Output findings as JSON}
                            {--fix : Attempt to fix auto-fixable items (not implemented yet)}';

    protected $description = 'Run security audit checks (config, runtime, logging)';

    public function handle(SecurityAuditService $audit): int
    {
        $findings = $audit->run();

        if ($this->option('json')) {
            $this->line(json_encode(['findings' => $findings], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if (count($findings) === 0) {
            $this->info('No security findings.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($findings as $f) {
            $rows[] = [
                $f['check_id'],
                $f['severity'],
                $f['message'],
                $f['fix'] ?? '—',
            ];
        }

        $this->table(['Check', 'Severity', 'Message', 'Fix'], $rows);

        $critical = count(array_filter($findings, fn ($f) => $f['severity'] === SecurityAuditService::SEVERITY_CRITICAL));
        if ($critical > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
