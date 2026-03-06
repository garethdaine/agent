<?php

declare(strict_types=1);

namespace App\Services\Agent;

class ConfigSchemaService
{
    public function getSchema(): array
    {
        return [
            'runtime' => [
                'label' => 'Runtime',
                'fields' => [
                    [
                        'key' => 'runtime.default_mode',
                        'envKey' => 'RUNTIME_DEFAULT_MODE',
                        'label' => 'Default mode',
                        'type' => 'select',
                        'options' => ['safe', 'standard', 'full'],
                        'description' => 'The default runtime mode for new sessions.',
                    ],
                    [
                        'key' => 'runtime.approval_model',
                        'envKey' => 'RUNTIME_APPROVAL_MODEL',
                        'label' => 'Approval model',
                        'type' => 'select',
                        'options' => ['strict', 'auto', 'policy'],
                        'description' => 'How tool call approvals are handled.',
                    ],
                    [
                        'key' => 'runtime.session_timeout',
                        'envKey' => 'RUNTIME_SESSION_TIMEOUT',
                        'label' => 'Session timeout (seconds)',
                        'type' => 'number',
                        'min' => 0,
                        'max' => 86400,
                        'description' => 'Idle timeout in seconds before sessions are auto-archived. 0 or empty for no timeout.',
                    ],
                    [
                        'key' => 'runtime.concurrent_session_limit_default',
                        'envKey' => 'RUNTIME_CONCURRENT_SESSION_LIMIT',
                        'label' => 'Max concurrent sessions',
                        'type' => 'number',
                        'min' => 1,
                        'max' => 20,
                        'description' => 'Maximum number of simultaneous active runtime sessions.',
                    ],
                    [
                        'key' => 'runtime.tool_deny',
                        'envKey' => 'RUNTIME_TOOL_DENY',
                        'label' => 'Tool deny list',
                        'type' => 'tags',
                        'description' => 'Tools that are always denied regardless of policy.',
                    ],
                    [
                        'key' => 'runtime.tool_allow',
                        'envKey' => 'RUNTIME_TOOL_ALLOW',
                        'label' => 'Tool allow list',
                        'type' => 'tags',
                        'description' => 'Tools that are auto-approved without user confirmation.',
                    ],
                ],
            ],
            'messenger' => [
                'label' => 'Messenger',
                'fields' => [
                    [
                        'key' => 'agent.default_runner_type',
                        'envKey' => 'AGENT_DEFAULT_RUNNER_TYPE',
                        'label' => 'Default runner',
                        'type' => 'select',
                        'options' => ['claude', 'codex', 'custom'],
                        'description' => 'The default runner for messenger-triggered tasks.',
                    ],
                    [
                        'key' => 'agent.streaming.enabled',
                        'envKey' => 'AGENT_STREAMING_ENABLED',
                        'label' => 'Streaming responses',
                        'type' => 'boolean',
                        'description' => 'Enable progressive message editing for streaming AI responses.',
                    ],
                    [
                        'key' => 'agent.streaming.chunk_size',
                        'envKey' => 'AGENT_STREAMING_CHUNK_SIZE',
                        'label' => 'Streaming chunk size',
                        'type' => 'number',
                        'min' => 500,
                        'max' => 4000,
                        'description' => 'Max character count per streaming message chunk.',
                    ],
                ],
            ],
            'security' => [
                'label' => 'Security',
                'fields' => [
                    [
                        'key' => 'logging.redact_sensitive',
                        'envKey' => 'LOG_REDACT_SENSITIVE',
                        'label' => 'Log redaction',
                        'type' => 'boolean',
                        'description' => 'Redact sensitive data from log output.',
                    ],
                    [
                        'key' => 'security.two_factor_available',
                        'label' => 'Two-Factor Auth',
                        'type' => 'boolean',
                        'readOnly' => true,
                        'description' => 'Whether two-factor authentication is available (requires Laravel Fortify).',
                    ],
                    [
                        'key' => 'security.api_tokens_enabled',
                        'label' => 'API Tokens',
                        'type' => 'boolean',
                        'readOnly' => true,
                        'description' => 'Whether API token management is enabled (requires Jetstream API feature).',
                    ],
                ],
            ],
            'agent' => [
                'label' => 'Agent',
                'fields' => [
                    [
                        'key' => 'agent.allowed_working_directory_bases',
                        'envKey' => 'AGENT_WORKING_DIRECTORY_BASES',
                        'label' => 'Allowed working directories',
                        'type' => 'tags',
                        'description' => 'Filesystem paths the agent is allowed to work within.',
                    ],
                    [
                        'key' => 'agent.runner_executables',
                        'label' => 'Runner executables',
                        'type' => 'tags',
                        'readOnly' => true,
                        'description' => 'Available runner executable names (configured via environment).',
                    ],
                    [
                        'key' => 'agent.forbidden_env_keys',
                        'label' => 'Forbidden env keys',
                        'type' => 'tags',
                        'readOnly' => true,
                        'description' => 'Environment variable keys that are never passed to agent processes.',
                    ],
                ],
            ],
            'webhooks' => [
                'label' => 'Webhooks',
                'fields' => [
                    [
                        'key' => 'agent.webhooks.enabled',
                        'envKey' => 'AGENT_WEBHOOKS_ENABLED',
                        'label' => 'Webhooks enabled',
                        'type' => 'boolean',
                        'description' => 'Enable webhook delivery for job run events.',
                    ],
                    [
                        'key' => 'agent.webhooks.url',
                        'envKey' => 'AGENT_WEBHOOK_URL',
                        'label' => 'Webhook URL',
                        'type' => 'url',
                        'description' => 'HTTPS endpoint to receive webhook payloads.',
                    ],
                ],
            ],
        ];
    }

    public function getCurrentValues(): array
    {
        $values = [];

        foreach ($this->getSchema() as $section) {
            foreach ($section['fields'] as $field) {
                $values[$field['key']] = config($field['key']);
            }
        }

        // Computed read-only values (not in config files)
        $values['security.two_factor_available'] = class_exists(\Laravel\Fortify\TwoFactorAuthenticatable::class);
        $values['security.api_tokens_enabled'] = in_array(
            \Laravel\Jetstream\Features::api(),
            config('jetstream.features', []),
        );

        // Transformed values
        $values['agent.runner_executables'] = array_keys(config('agent.runner_executables', []));

        return $values;
    }

    /**
     * Build a map from flat payload key (dots replaced with underscores) to field definition.
     *
     * @return array<string, array>
     */
    public function getEditableFieldMap(): array
    {
        $map = [];

        foreach ($this->getSchema() as $section) {
            foreach ($section['fields'] as $field) {
                if (! empty($field['readOnly']) || empty($field['envKey'])) {
                    continue;
                }

                $flatKey = str_replace('.', '_', $field['key']);
                $map[$flatKey] = $field;
            }
        }

        return $map;
    }

    public function getValidationRules(): array
    {
        $rules = [];

        foreach ($this->getSchema() as $section) {
            foreach ($section['fields'] as $field) {
                if (! empty($field['readOnly'])) {
                    continue;
                }

                $fieldRules = ['sometimes'];

                $fieldRules[] = match ($field['type']) {
                    'select' => 'in:'.implode(',', $field['options']),
                    'number' => 'nullable|integer|min:'.($field['min'] ?? 0).'|max:'.($field['max'] ?? 99999),
                    'boolean' => 'boolean',
                    'url' => 'nullable|url|max:2048',
                    'tags' => 'array',
                    default => 'string|max:255',
                };

                $rules[$field['key']] = $fieldRules;
            }
        }

        return $rules;
    }
}
