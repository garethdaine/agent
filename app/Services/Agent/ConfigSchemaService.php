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
                        'label' => 'Default mode',
                        'type' => 'select',
                        'options' => ['autonomous', 'supervised', 'locked'],
                        'description' => 'The default runtime mode for new sessions.',
                    ],
                    [
                        'key' => 'runtime.approval_model',
                        'label' => 'Approval model',
                        'type' => 'select',
                        'options' => ['auto', 'manual', 'policy'],
                        'description' => 'How tool call approvals are handled.',
                    ],
                    [
                        'key' => 'runtime.session_timeout_minutes',
                        'label' => 'Session timeout (minutes)',
                        'type' => 'number',
                        'min' => 5,
                        'max' => 1440,
                        'description' => 'Idle timeout before sessions are auto-archived.',
                    ],
                    [
                        'key' => 'runtime.concurrent_session_limit',
                        'label' => 'Max concurrent sessions',
                        'type' => 'number',
                        'min' => 1,
                        'max' => 20,
                        'description' => 'Maximum number of simultaneous active runtime sessions.',
                    ],
                    [
                        'key' => 'runtime.tool_deny',
                        'label' => 'Tool deny list',
                        'type' => 'tags',
                        'description' => 'Tools that are always denied regardless of policy.',
                    ],
                    [
                        'key' => 'runtime.tool_allow',
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
                        'label' => 'Default runner',
                        'type' => 'select',
                        'options' => ['claude', 'codex', 'custom'],
                        'description' => 'The default runner for messenger-triggered tasks.',
                    ],
                    [
                        'key' => 'agent.streaming.enabled',
                        'label' => 'Streaming responses',
                        'type' => 'boolean',
                        'description' => 'Enable progressive message editing for streaming AI responses.',
                    ],
                    [
                        'key' => 'agent.streaming.chunk_size',
                        'label' => 'Streaming chunk size',
                        'type' => 'number',
                        'min' => 500,
                        'max' => 4000,
                        'description' => 'Max character count per streaming message chunk.',
                    ],
                ],
            ],
            'webhooks' => [
                'label' => 'Webhooks',
                'fields' => [
                    [
                        'key' => 'agent.webhooks.enabled',
                        'label' => 'Webhooks enabled',
                        'type' => 'boolean',
                        'description' => 'Enable webhook delivery for job run events.',
                    ],
                    [
                        'key' => 'agent.webhooks.url',
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

        return $values;
    }

    public function getValidationRules(): array
    {
        $rules = [];

        foreach ($this->getSchema() as $section) {
            foreach ($section['fields'] as $field) {
                $fieldRules = ['sometimes'];

                $fieldRules[] = match ($field['type']) {
                    'select' => 'in:'.implode(',', $field['options']),
                    'number' => 'integer|min:'.($field['min'] ?? 0).'|max:'.($field['max'] ?? 99999),
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
