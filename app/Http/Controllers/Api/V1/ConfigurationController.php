<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\ConfigSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ConfigurationController extends Controller
{
    public function index(ConfigSchemaService $schema): JsonResponse
    {
        return response()->json([
            'data' => [
                'schema' => $schema->getSchema(),
                'values' => $schema->getCurrentValues(),
                'runtime' => [
                    'default_mode' => config('runtime.default_mode'),
                    'approval_model' => config('runtime.approval_model'),
                    'tool_deny' => config('runtime.tool_deny', []),
                    'tool_allow' => config('runtime.tool_allow', []),
                    'session_timeout_minutes' => config('runtime.session_timeout_minutes', 120),
                    'concurrent_session_limit' => config('runtime.concurrent_session_limit', 3),
                ],
                'messenger' => [
                    'default_runner_type' => config('agent.default_runner_type', 'claude'),
                    'streaming_enabled' => config('agent.streaming.enabled', false),
                    'streaming_chunk_size' => config('agent.streaming.chunk_size', 1800),
                ],
                'security' => [
                    'log_redaction_enabled' => config('logging.redact_sensitive', false),
                    'two_factor_available' => class_exists(\Laravel\Fortify\TwoFactorAuthenticatable::class),
                    'api_tokens_enabled' => in_array(
                        \Laravel\Jetstream\Features::api(),
                        config('jetstream.features', []),
                    ),
                ],
                'agent' => [
                    'allowed_working_directory_bases' => config('agent.allowed_working_directory_bases', []),
                    'runner_executables' => array_keys(config('agent.runner_executables', [])),
                    'forbidden_env_keys' => config('agent.forbidden_env_keys', []),
                ],
                'webhooks' => [
                    'enabled' => config('agent.webhooks.enabled', false),
                    'url' => config('agent.webhooks.url'),
                    'events' => config('agent.webhooks.events', []),
                ],
            ],
        ]);
    }

    public function update(Request $request, ConfigSchemaService $schema): JsonResponse
    {
        $rules = $schema->getValidationRules();

        $validated = $request->validate(
            collect($rules)
                ->mapWithKeys(fn (array $r, string $key) => [str_replace('.', '_', $key) => $r])
                ->all()
        );

        $changes = [];
        $envUpdates = [];

        foreach ($validated as $flatKey => $value) {
            $dotKey = str_replace('_', '.', $flatKey);
            $envKey = $this->configKeyToEnv($dotKey);
            if ($envKey) {
                $envUpdates[$envKey] = is_bool($value) ? ($value ? 'true' : 'false')
                    : (is_array($value) ? implode(',', $value) : (string) $value);
                $changes[$dotKey] = $value;
            }
        }

        if (empty($changes)) {
            return response()->json(['data' => ['updated' => false, 'message' => 'No changes.']]);
        }

        $this->writeEnvValues($envUpdates);

        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
            // Non-fatal: config will refresh on next request
        }

        return response()->json([
            'data' => [
                'updated' => true,
                'changes' => array_keys($changes),
                'values' => $schema->getCurrentValues(),
            ],
        ]);
    }

    private function configKeyToEnv(string $key): ?string
    {
        return match ($key) {
            'runtime.default.mode' => 'RUNTIME_DEFAULT_MODE',
            'runtime.approval.model' => 'RUNTIME_APPROVAL_MODEL',
            'runtime.session.timeout.minutes' => 'RUNTIME_SESSION_TIMEOUT_MINUTES',
            'runtime.concurrent.session.limit' => 'RUNTIME_CONCURRENT_SESSION_LIMIT',
            'agent.default.runner.type' => 'AGENT_DEFAULT_RUNNER_TYPE',
            'agent.streaming.enabled' => 'AGENT_STREAMING_ENABLED',
            'agent.streaming.chunk.size' => 'AGENT_STREAMING_CHUNK_SIZE',
            'agent.webhooks.enabled' => 'AGENT_WEBHOOKS_ENABLED',
            'agent.webhooks.url' => 'AGENT_WEBHOOK_URL',
            default => null,
        };
    }

    private function writeEnvValues(array $values): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') ? '"'.$value.'"' : $value;

            if (preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
                $content = preg_replace(
                    '/^'.preg_quote($key, '/').'=.*/m',
                    $key.'='.$escaped,
                    $content
                );
            } else {
                $content .= "\n".$key.'='.$escaped;
            }
        }

        file_put_contents($envPath, $content);
    }
}
