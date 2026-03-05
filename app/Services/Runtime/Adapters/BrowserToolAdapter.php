<?php

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use Illuminate\Support\Facades\Process;

class BrowserToolAdapter extends AbstractToolAdapter
{
    private const READ_ONLY_OPERATIONS = ['screenshot', 'extract'];

    private const MUTATION_OPERATIONS = ['navigate', 'click', 'type'];

    public function name(): string
    {
        return 'browser';
    }

    public function schema(): array
    {
        return [
            'operations' => ['navigate', 'click', 'type', 'screenshot', 'extract'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'url' => ['type' => 'string', 'required_for' => ['navigate']],
                'selector' => ['type' => 'string', 'required_for' => ['click', 'type', 'extract']],
                'text' => ['type' => 'string', 'required_for' => ['type']],
                'path' => ['type' => 'string', 'optional' => true],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        $operation = $args['operation'] ?? '';

        if ($context->mode === RuntimeMode::Safe) {
            return in_array($operation, self::READ_ONLY_OPERATIONS, true);
        }

        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        $operation = $args['operation'] ?? '';

        if (in_array($operation, self::READ_ONLY_OPERATIONS, true)) {
            return 'browser_snapshot';
        }

        return 'browser_action';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? '';

        $allowed = config('runtime.browser.allowed_commands', []);
        if (! is_array($allowed) || ! in_array($operation, $allowed, true)) {
            return ToolResult::failure(
                "Browser operation not allowed: {$operation}",
                $this->duration($startTime)
            );
        }

        $binary = config('runtime.browser.sidecar_binary', '/usr/local/bin/agent-browser');
        if ($binary === '' || ! is_executable($binary)) {
            return ToolResult::failure(
                'Browser sidecar binary not configured or not executable (AGENT_BROWSER_PATH)',
                $this->duration($startTime)
            );
        }

        $cmd = $this->buildCommand($binary, $operation, $args);

        try {
            $result = Process::timeout(60)->run($cmd);

            if (! $result->successful()) {
                return ToolResult::failure(
                    'Browser sidecar failed: '.trim($result->errorOutput() ?: $result->output()),
                    $this->duration($startTime)
                );
            }

            $output = trim($result->output());

            return ToolResult::success([
                'operation' => $operation,
                'output' => $output,
                'success' => true,
            ], $this->duration($startTime));
        } catch (\Throwable $e) {
            return ToolResult::failure(
                'Browser tool error: '.$e->getMessage(),
                $this->duration($startTime)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<int, string>
     */
    private function buildCommand(string $binary, string $operation, array $args): array
    {
        $cmd = [$binary, $operation];

        foreach ($args as $key => $value) {
            if ($key === 'operation' || $value === null || $value === '') {
                continue;
            }
            if (is_scalar($value)) {
                $cmd[] = '--'.$key.'='.$value;
            }
        }

        return $cmd;
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
