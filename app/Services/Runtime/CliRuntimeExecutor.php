<?php

namespace App\Services\Runtime;

use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Executes a single runtime turn by running the CLI (claude/codex) with the user message as a task.
 * Uses the credential manager for the Anthropic API key; no env var required.
 */
class CliRuntimeExecutor
{
    public function __construct(
        private CredentialsManager $credentialsManager,
    ) {}

    /**
     * Run one turn via the CLI: create temp task file, run runner, capture output, return result.
     *
     * @return array{status: 'completed'|'failed', text?: string, error?: string}
     */
    public function executeTurn(RuntimeSession $session, string $userMessage): array
    {
        $session->load('user');
        $user = $session->user;
        if ($user === null) {
            return ['status' => 'failed', 'error' => 'Runtime session has no user.'];
        }

        $apiKey = $this->credentialsManager->get($user, 'anthropic', 'api_key');
        if ($apiKey === null || $apiKey === '') {
            return [
                'status' => 'failed',
                'error' => 'Anthropic API key not found. Add your key in the credential manager (Settings → Credentials → Anthropic).',
            ];
        }

        $runnerType = config('runtime.cli.runner_type', 'claude');
        $executables = config('agent.runner_executables', []);
        $templates = config('agent.default_templates', []);
        $executable = $executables[$runnerType] ?? null;
        $template = $templates[$runnerType] ?? null;

        if ($executable === null || $template === null) {
            return [
                'status' => 'failed',
                'error' => "Runtime CLI runner '{$runnerType}' is not configured (runner_executables / default_templates).",
            ];
        }

        $taskBase = storage_path('app/memory/context');
        if (! File::isDirectory($taskBase)) {
            File::makeDirectory($taskBase, 0755, true);
        }

        $taskPath = $taskBase.'/runtime-'.Str::uuid()->toString().'.md';
        $workingDir = $session->workspace_root !== null && $session->workspace_root !== ''
            ? $session->workspace_root
            : (config('agent.allowed_working_directory_bases', [])[0] ?? base_path());

        $written = File::put($taskPath, $userMessage, true);
        if ($written === false) {
            return ['status' => 'failed', 'error' => 'Failed to write runtime task file.'];
        }

        try {
            $command = $this->buildCommand($template, $taskPath, $workingDir);
            $parentEnv = array_merge($_ENV ?? [], $_SERVER ?? []);
            $env = array_merge(
                array_filter($parentEnv, static fn ($_, string $k): bool => ! str_starts_with($k, 'ANTHROPIC_'), ARRAY_FILTER_USE_BOTH),
                ['ANTHROPIC_API_KEY' => $apiKey]
            );
            if (config('runtime.browser.headed', false)) {
                $env['AGENT_BROWSER_HEADED'] = '1';
            }

            Log::info('CliRuntimeExecutor: Starting CLI process', [
                'session_id' => $session->id,
                'runner_type' => $runnerType,
                'timeout_seconds' => (int) config('runtime.cli.timeout_seconds', 300),
            ]);

            $process = new Process($command, $workingDir, $env);
            $process->setTimeout((int) config('runtime.cli.timeout_seconds', 300));
            $process->run();

            Log::info('CliRuntimeExecutor: CLI process finished', [
                'session_id' => $session->id,
                'exit_code' => $process->getExitCode(),
                'successful' => $process->isSuccessful(),
            ]);

            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
            $text = $this->extractFinalText($stdout, $runnerType);

            if ($process->isSuccessful()) {
                return [
                    'status' => 'completed',
                    'text' => $text !== '' ? $text : trim($stdout) !== '' ? trim($stdout) : 'Done.',
                ];
            }

            $error = trim($stderr) !== '' ? trim($stderr) : 'CLI process exited with code '.$process->getExitCode();
            if (strlen($error) > 500) {
                $error = substr($error, 0, 497).'…';
            }

            return ['status' => 'failed', 'error' => $error];
        } finally {
            if (File::exists($taskPath)) {
                File::delete($taskPath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $template, string $taskPath, string $workingDir): array
    {
        $rendered = str_replace(
            ['{{task_markdown_path}}', '{{working_directory}}'],
            [$taskPath, $workingDir],
            $template
        );

        return array_values(array_filter(preg_split('/\s+/', $rendered) ?: [], static fn (string $t): bool => $t !== ''));
    }

    private function extractFinalText(string $stdout, string $runnerType): string
    {
        $lines = preg_split('/\R/', trim($stdout)) ?: [];
        $fragments = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            if ($runnerType === 'claude') {
                $type = $decoded['type'] ?? '';
                if ($type === 'content_block_delta') {
                    $delta = $decoded['delta'] ?? [];
                    $text = is_array($delta) ? ($delta['text'] ?? '') : (is_string($delta) ? $delta : '');
                    if ($text !== '') {
                        $fragments[] = $text;
                    }
                }
            }

            if ($runnerType === 'codex') {
                $text = $decoded['text'] ?? $decoded['content'] ?? $decoded['message'] ?? '';
                if (is_string($text) && $text !== '') {
                    $fragments[] = $text;
                }
            }
        }

        return implode('', $fragments);
    }
}
