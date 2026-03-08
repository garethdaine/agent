<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for batch updating memory settings.
 */
class UpdateMemorySettingsRequest extends FormRequest
{
    /**
     * Valid setting keys that can be updated.
     *
     * @var array<string>
     */
    public const VALID_SETTING_KEYS = [
        'memory_enabled',
        'api_features_enabled',
        'extraction_provider',
        'extraction_model',
        'summarization_provider',
        'summarization_model',
        'embeddings_provider',
        'embeddings_model',
        'embedding_dimensions',
        'context_budget_percent',
        'context_floor_tokens',
        'context_ceiling_tokens',
        'context_margin_percent',
        'embedding_decay_threshold',
        'graph_importance_threshold',
        'graph_retention_days',
        'working_memory_max_messages',
        'working_memory_ttl_seconds',
        'openai_rpm',
        'openai_tpm',
        'anthropic_rpm',
        'anthropic_tpm',
        'provider_key_openai',
        'provider_key_anthropic',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['nullable'],
        ];
    }

    /**
     * Configure the validator to check setting keys.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->input('settings', []);

            foreach (array_keys($settings) as $key) {
                if (! in_array($key, self::VALID_SETTING_KEYS, true)) {
                    $validator->errors()->add(
                        "settings.{$key}",
                        "Invalid setting key: {$key}"
                    );
                }
            }
        });
    }

    /**
     * Get the validated settings array.
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->input('settings', []);
    }
}
