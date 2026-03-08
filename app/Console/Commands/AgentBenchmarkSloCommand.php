<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\SchedulerHeartbeat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AgentBenchmarkSloCommand extends Command
{
    protected $signature = 'agent:benchmark-slo
        {--seed : Seed benchmark dataset (100 jobs, 2000 runs, 100k events by default)}
        {--measure : Run endpoint latency measurements}
        {--jobs=100 : Number of jobs to seed}
        {--runs=2000 : Number of runs to seed}
        {--events=100000 : Number of events to seed}
        {--requests=200 : Number of repeated requests per endpoint benchmark}
        {--json : Output JSON summary}';

    protected $description = 'Seed benchmark dataset and validate API p95 SLO targets for Phase 9.';

    public function handle(Kernel $kernel): int
    {
        $seed = (bool) $this->option('seed');
        $measure = (bool) $this->option('measure');

        if (! $seed && ! $measure) {
            $seed = true;
            $measure = true;
        }

        $jobs = max(1, (int) $this->option('jobs'));
        $runs = max(1, (int) $this->option('runs'));
        $events = max(1, (int) $this->option('events'));
        $requests = max(1, (int) $this->option('requests'));

        $user = $this->resolveBenchmarkUser();
        $jobIds = [];
        $seedSummary = null;

        if ($seed) {
            $this->info(sprintf('Seeding dataset: jobs=%d runs=%d events=%d', $jobs, $runs, $events));
            $seedSummary = $this->seedDataset($user, $jobs, $runs, $events);
            $jobIds = $seedSummary['job_ids'];
        }

        if ($jobIds === []) {
            $jobIds = AgentJob::query()
                ->where('user_id', $user->id)
                ->where('name', 'like', 'Benchmark Job %')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if ($measure && $jobIds === []) {
            $this->error('No benchmark jobs found. Run with --seed first.');

            return self::FAILURE;
        }

        $measureSummary = null;
        if ($measure) {
            $this->info(sprintf('Running benchmark requests: %d iterations per endpoint', $requests));
            $measureSummary = $this->measureBenchmarks($kernel, $user, $jobIds, $requests);
        }

        $summary = [
            'seeded' => $seedSummary,
            'measured' => $measureSummary,
            'targets_ms' => [
                'jobs_index_p95_lt' => 500,
                'run_now_ack_p95_lt' => 400,
                'monitor_poll_p95_lt' => 700,
            ],
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        if ($seedSummary !== null) {
            $this->line(sprintf(
                'Seed complete: jobs=%d runs=%d events=%d',
                $seedSummary['jobs_created'],
                $seedSummary['runs_created'],
                $seedSummary['events_created'],
            ));
        }

        if ($measureSummary !== null) {
            foreach ($measureSummary as $name => $result) {
                $this->line(sprintf(
                    '[%s] p95=%0.2fms avg=%0.2fms min=%0.2fms max=%0.2fms pass=%s statuses=%s',
                    $name,
                    $result['p95_ms'],
                    $result['avg_ms'],
                    $result['min_ms'],
                    $result['max_ms'],
                    $result['passes_target'] ? 'yes' : 'no',
                    json_encode($result['statuses']),
                ));
            }
        }

        return self::SUCCESS;
    }

    private function resolveBenchmarkUser(): User
    {
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => 'agent-benchmark@example.test'],
            [
                'name' => 'Agent Benchmark',
                'password' => bcrypt(Str::random(40)),
            ]
        );

        return $user;
    }

    /**
     * @return array{jobs_created:int,runs_created:int,events_created:int,job_ids:array<int, int>}
     */
    private function seedDataset(User $user, int $jobs, int $runs, int $events): array
    {
        SchedulerHeartbeat::query()->updateOrCreate(
            ['source' => 'scheduler_dispatch'],
            [
                'last_seen_at' => CarbonImmutable::now('UTC'),
                'meta_json' => ['seeded_by' => 'agent:benchmark-slo'],
            ]
        );

        AgentJob::query()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->where('name', 'like', 'Benchmark Job %')
            ->get()
            ->each(fn (AgentJob $job) => $job->forceDelete());

        $taskDir = storage_path('app/agent-benchmark');
        if (! is_dir($taskDir)) {
            @mkdir($taskDir, 0777, true);
        }

        $taskPath = $taskDir.'/benchmark-task.md';
        if (! file_exists($taskPath)) {
            file_put_contents($taskPath, "# Benchmark Task\n\nCollect benchmark metrics.\n");
        }

        $defaultTemplate = (string) (config('agent.default_templates.codex') ?? '/opt/homebrew/bin/codex -m gpt-5.3-codex exec {{task_markdown_path}}');
        $now = CarbonImmutable::now('UTC');
        $jobRows = [];

        for ($i = 1; $i <= $jobs; $i++) {
            $jobRows[] = [
                'user_id' => $user->id,
                'name' => sprintf('Benchmark Job %03d', $i),
                'description' => 'Phase 9 benchmark seed job',
                'cron_expression' => '*/5 * * * *',
                'timezone' => 'UTC',
                // Keep seeded benchmark jobs disabled so they do not interfere with real scheduler traffic.
                'is_enabled' => false,
                'max_runtime_seconds' => 900,
                'cooldown_seconds' => 0,
                'runner_type' => 'codex',
                'command_template' => $defaultTemplate,
                'task_markdown_path' => $taskPath,
                'working_directory' => base_path(),
                'env_json' => null,
                'last_validated_executable_path' => null,
                'scheduled_path_failure_streak' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('agent_jobs')->insert($jobRows);

        $jobIds = AgentJob::query()
            ->where('user_id', $user->id)
            ->where('name', 'like', 'Benchmark Job %')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $runStatuses = [
            AgentJobRun::STATUS_SUCCEEDED,
            AgentJobRun::STATUS_FAILED,
            AgentJobRun::STATUS_TIMED_OUT,
            AgentJobRun::STATUS_KILLED,
            AgentJobRun::STATUS_SKIPPED,
            AgentJobRun::STATUS_QUEUED,
        ];
        $runIds = [];

        for ($i = 0; $i < $runs; $i++) {
            $status = $runStatuses[$i % count($runStatuses)];
            $createdAt = $now->subSeconds($i * 15);
            $startedAt = in_array($status, [AgentJobRun::STATUS_QUEUED], true) ? null : $createdAt->addSeconds(1);
            $finishedAt = in_array($status, AgentJobRun::TERMINAL_STATUSES, true)
                ? $createdAt->addSeconds(12)
                : null;

            $durationMs = $finishedAt !== null
                ? max(1, ((int) ($finishedAt->getTimestampMs() - $startedAt->getTimestampMs())))
                : 0;

            $runIds[] = (int) DB::table('agent_job_runs')->insertGetId([
                'agent_job_id' => $jobIds[$i % count($jobIds)],
                'user_id' => $user->id,
                'initiated_by_user_id' => $user->id,
                'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
                'due_window_utc_minute' => null,
                'status' => $status,
                'pid' => null,
                'resolved_executable_path' => null,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'exit_code' => $status === AgentJobRun::STATUS_SUCCEEDED ? 0 : null,
                'signal' => null,
                'duration_ms' => $durationMs,
                'stdout_bytes_pre' => 0,
                'stdout_bytes_post' => 0,
                'stderr_bytes_pre' => 0,
                'stderr_bytes_post' => 0,
                'error_summary' => null,
                'error_code' => null,
                'metadata_json' => json_encode([
                    'output_truncated' => false,
                    'redaction_count' => 0,
                    'approval_required' => false,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $eventsPerRun = max(1, intdiv($events, max(1, count($runIds))));
        $remainder = $events - ($eventsPerRun * count($runIds));
        $eventsCreated = 0;
        $eventRows = [];
        $eventNow = CarbonImmutable::now('UTC');

        foreach ($runIds as $runIndex => $runId) {
            $eventCount = $eventsPerRun + ($runIndex < $remainder ? 1 : 0);

            for ($sequence = 1; $sequence <= $eventCount; $sequence++) {
                $eventRows[] = [
                    'agent_job_run_id' => $runId,
                    'event_type' => $sequence % 5 === 0 ? 'lifecycle' : 'stdout',
                    'sequence' => $sequence,
                    'payload' => $sequence % 5 === 0
                        ? json_encode(['type' => 'state_transition', 'to' => 'running'], JSON_UNESCAPED_SLASHES)
                        : sprintf('benchmark-output-%d', $sequence),
                    'event_ts' => $eventNow,
                    'created_at' => $eventNow,
                    'updated_at' => $eventNow,
                ];

                if (count($eventRows) >= 2000) {
                    DB::table('agent_run_events')->insert($eventRows);
                    $eventsCreated += count($eventRows);
                    $eventRows = [];
                }
            }
        }

        if ($eventRows !== []) {
            DB::table('agent_run_events')->insert($eventRows);
            $eventsCreated += count($eventRows);
        }

        return [
            'jobs_created' => count($jobIds),
            'runs_created' => count($runIds),
            'events_created' => $eventsCreated,
            'job_ids' => $jobIds,
        ];
    }

    /**
     * @param  array<int, int>  $jobIds
     * @return array<string, array<string, mixed>>
     */
    private function measureBenchmarks(Kernel $kernel, User $user, array $jobIds, int $requests): array
    {
        $user->tokens()->where('name', 'agent-benchmark-slo')->delete();
        $token = $user->createToken('agent-benchmark-slo')->plainTextToken;
        $runNowPool = $this->prepareRunNowPool(max(1, (int) ceil($requests / 25)));
        $originalMutationLimiter = RateLimiter::limiter('agent-mutations');
        RateLimiter::for('agent-mutations', fn () => [Limit::none()]);

        try {
            $results = [];

            $results['jobs_index'] = $this->measureEndpoint(
                $requests,
                fn (int $index): int => $this->dispatchApiRequest(
                    $kernel,
                    'GET',
                    '/agent/api/v1/jobs',
                    $token,
                    ['per_page' => 25, 'page' => 1, 'sort' => 'updated_at', 'dir' => 'desc']
                )['status'],
                500
            );

            $results['run_now_ack'] = $this->measureEndpoint(
                $requests,
                function (int $index) use ($kernel, $runNowPool): int {
                    $target = $runNowPool[$index % count($runNowPool)];
                    $jobId = (int) $target['job_id'];
                    $userId = (int) $target['user_id'];
                    $this->forceTerminalizeActiveRuns($jobId);
                    $this->forgetRunNowIdempotencyKey($userId, $jobId);

                    $response = $this->dispatchApiRequest(
                        $kernel,
                        'POST',
                        '/agent/api/v1/jobs/'.$jobId.'/run-now',
                        (string) $target['token'],
                    );

                    $runId = (int) data_get($response, 'json.data.run_id', 0);
                    if ($response['status'] === 202 && $runId > 0) {
                        $this->forceCompleteRun($runId);
                    }

                    return $response['status'];
                },
                400
            );

            $results['monitor_poll'] = $this->measureEndpoint(
                $requests,
                function () use ($kernel, $token): int {
                    $runsResponse = $this->dispatchApiRequest(
                        $kernel,
                        'GET',
                        '/agent/api/v1/runs',
                        $token,
                        ['hours' => 24, 'limit' => 100]
                    );

                    if ($runsResponse['status'] !== 200) {
                        return $runsResponse['status'];
                    }

                    $runId = (int) data_get($runsResponse, 'json.data.0.id', 0);
                    if ($runId <= 0) {
                        return 200;
                    }

                    $eventsResponse = $this->dispatchApiRequest(
                        $kernel,
                        'GET',
                        '/agent/api/v1/runs/'.$runId.'/events',
                        $token,
                        ['after_sequence' => 0, 'limit' => 100]
                    );

                    return $eventsResponse['status'];
                },
                700
            );

            return $results;
        } finally {
            if ($originalMutationLimiter !== null) {
                RateLimiter::for('agent-mutations', $originalMutationLimiter);
            }

            $user->tokens()->where('name', 'agent-benchmark-slo')->delete();
            foreach ($runNowPool as $target) {
                /** @var User $poolUser */
                $poolUser = $target['user'];
                $poolUser->tokens()->where('name', 'agent-benchmark-run-now')->delete();
            }
        }
    }

    /**
     * @return array<int, array{user:User,user_id:int,job_id:int,token:string}>
     */
    private function prepareRunNowPool(int $poolSize): array
    {
        $taskDir = storage_path('app/agent-benchmark');
        if (! is_dir($taskDir)) {
            @mkdir($taskDir, 0777, true);
        }

        $taskPath = $taskDir.'/benchmark-task.md';
        if (! file_exists($taskPath)) {
            file_put_contents($taskPath, "# Benchmark Task\n\nCollect benchmark metrics.\n");
        }

        $defaultTemplate = (string) (config('agent.default_templates.codex') ?? '/opt/homebrew/bin/codex -m gpt-5.3-codex exec {{task_markdown_path}}');
        $pool = [];

        for ($index = 1; $index <= $poolSize; $index++) {
            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['email' => sprintf('agent-benchmark-runnow-%d@example.test', $index)],
                [
                    'name' => sprintf('Agent Benchmark RunNow %d', $index),
                    'password' => bcrypt(Str::random(40)),
                ]
            );

            $jobName = sprintf('Benchmark RunNow Job %02d', $index);
            /** @var AgentJob $job */
            $job = AgentJob::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $jobName],
                [
                    'description' => 'Run-now benchmark job',
                    'cron_expression' => '*/5 * * * *',
                    'timezone' => 'UTC',
                    'is_enabled' => true,
                    'max_runtime_seconds' => 900,
                    'cooldown_seconds' => 0,
                    'runner_type' => 'codex',
                    'command_template' => $defaultTemplate,
                    'task_markdown_path' => $taskPath,
                    'working_directory' => base_path(),
                    'env_json' => null,
                    'last_validated_executable_path' => null,
                    'scheduled_path_failure_streak' => 0,
                ]
            );

            $user->tokens()->where('name', 'agent-benchmark-run-now')->delete();
            $token = $user->createToken('agent-benchmark-run-now')->plainTextToken;

            $pool[] = [
                'user' => $user,
                'user_id' => (int) $user->id,
                'job_id' => (int) $job->id,
                'token' => $token,
            ];
        }

        return $pool;
    }

    /**
     * @return array{status:int,json:array<string,mixed>|null}
     */
    private function dispatchApiRequest(
        Kernel $kernel,
        string $method,
        string $uri,
        string $token,
        array $payload = []
    ): array {
        app('auth')->forgetGuards();

        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ];

        $method = strtoupper($method);
        $content = null;
        $parameters = [];

        if ($method === 'GET') {
            $parameters = $payload;
        } else {
            $content = $payload === [] ? '{}' : (json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        $request = Request::create(
            uri: $uri,
            method: $method,
            parameters: $parameters,
            cookies: [],
            files: [],
            server: $server,
            content: $content
        );

        $response = $kernel->handle($request);
        $body = $response->getContent();
        $decoded = is_string($body) ? json_decode($body, true) : null;
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    /**
     * @param  callable(int):int  $callback
     * @return array<string, mixed>
     */
    private function measureEndpoint(int $iterations, callable $callback, float $targetP95Ms): array
    {
        $latencies = [];
        $statuses = [];

        for ($index = 0; $index < $iterations; $index++) {
            $startedAt = hrtime(true);
            $status = $callback($index);
            $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

            $latencies[] = $elapsedMs;
            $statuses[$status] = (int) ($statuses[$status] ?? 0) + 1;
        }

        sort($latencies);

        $p95 = $this->percentile($latencies, 95);
        $avg = array_sum($latencies) / max(1, count($latencies));

        return [
            'iterations' => $iterations,
            'p95_ms' => round($p95, 2),
            'avg_ms' => round($avg, 2),
            'min_ms' => round($latencies[0] ?? 0, 2),
            'max_ms' => round($latencies[count($latencies) - 1] ?? 0, 2),
            'passes_target' => $p95 < $targetP95Ms,
            'target_p95_lt_ms' => $targetP95Ms,
            'statuses' => $statuses,
        ];
    }

    /**
     * @param  array<int, float>  $sorted
     */
    private function percentile(array $sorted, int $percentile): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $count = count($sorted);
        $index = (int) ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($count - 1, $index));

        return (float) $sorted[$index];
    }

    private function forgetRunNowIdempotencyKey(int $userId, int $jobId): void
    {
        try {
            $fingerprint = hash('sha256', sprintf('run-now|%d|%d', $userId, $jobId));
            Cache::store('redis')->forget('agent:run-now:'.$fingerprint);
        } catch (\Throwable) {
            // Ignore cache connectivity issues during benchmark cleanup.
        }
    }

    private function forceTerminalizeActiveRuns(int $jobId): void
    {
        $now = CarbonImmutable::now('UTC');
        $activeRuns = AgentJobRun::query()
            ->where('agent_job_id', $jobId)
            ->whereIn('status', AgentJobRun::ACTIVE_STATUSES)
            ->get();

        foreach ($activeRuns as $run) {
            if ($run->started_at === null) {
                $run->started_at = $now->toMutable();
            }

            $run->status = AgentJobRun::STATUS_SUCCEEDED;
            $run->finished_at = $now->toMutable();
            $run->duration_ms = max(1, (int) $run->started_at->diffInMilliseconds($now));
            $run->exit_code = 0;
            $run->signal = null;
            $run->save();
        }
    }

    private function forceCompleteRun(int $runId): void
    {
        $run = AgentJobRun::query()->find($runId);
        if ($run === null || in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        $startedAt = $run->started_at ?? $run->created_at ?? $now;

        $run->status = AgentJobRun::STATUS_SUCCEEDED;
        $run->started_at = $startedAt instanceof CarbonImmutable ? $startedAt->toMutable() : $startedAt;
        $run->finished_at = $now->toMutable();
        $run->duration_ms = max(1, (int) CarbonImmutable::parse($startedAt)->diffInMilliseconds($now));
        $run->exit_code = 0;
        $run->signal = null;
        $run->save();
    }
}
