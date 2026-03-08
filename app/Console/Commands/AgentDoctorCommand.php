<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Agent\DiagnosticsService;
use Illuminate\Console\Command;

class AgentDoctorCommand extends Command
{
    protected $signature = 'agent:doctor
                            {--json : Output results as JSON}';

    protected $description = 'Run diagnostics (database, Redis, queue, scheduler, messenger, storage)';

    public function handle(DiagnosticsService $diagnostics): int
    {
        $checks = $diagnostics->run();

        if ($this->option('json')) {
            $this->line(json_encode(['checks' => $checks], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if (count($checks) === 0) {
            $this->info('No checks run.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($checks as $c) {
            $rows[] = [
                $c['check_id'],
                $c['status'],
                $c['message'],
                $c['latency_ms'] !== null ? $c['latency_ms'].' ms' : '—',
                $c['fix'] ?? '—',
            ];
        }

        $this->table(['Check', 'Status', 'Message', 'Latency', 'Fix'], $rows);

        $errors = count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_ERROR));
        if ($errors > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
