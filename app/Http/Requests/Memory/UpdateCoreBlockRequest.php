<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use App\Models\MemoryCoreBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for creating/updating core memory blocks.
 */
class UpdateCoreBlockRequest extends FormRequest
{
    /**
     * Valid block keys that can be created/updated.
     *
     * @var array<string>
     */
    public const VALID_BLOCK_KEYS = [
        'agent_persona',
        'user_profile',
        'task_state',
        'tool_results_cache',
        'active_goals',
    ];

    /**
     * Identity block keys (user-only write).
     *
     * @var array<string>
     */
    public const IDENTITY_BLOCK_KEYS = [
        'agent_persona',
        'user_profile',
    ];

    /**
     * Operational block keys (agent-editable).
     *
     * @var array<string>
     */
    public const OPERATIONAL_BLOCK_KEYS = [
        'task_state',
        'tool_results_cache',
        'active_goals',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required'],
            'classification' => [
                'sometimes',
                'string',
                Rule::in(MemoryCoreBlock::$validClassifications),
            ],
            'job_id' => ['sometimes', 'nullable', 'integer', 'exists:agent_jobs,id'],
        ];
    }

    /**
     * Configure the validator to check block key validity.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $blockKey = $this->route('key');

            if (! in_array($blockKey, self::VALID_BLOCK_KEYS, true)) {
                $validator->errors()->add(
                    'key',
                    "Invalid block key: {$blockKey}. Valid keys are: ".implode(', ', self::VALID_BLOCK_KEYS)
                );
            }
        });
    }

    /**
     * Determine block type from key.
     */
    public function getBlockType(): string
    {
        $blockKey = $this->route('key');

        return in_array($blockKey, self::IDENTITY_BLOCK_KEYS, true)
            ? MemoryCoreBlock::TYPE_IDENTITY
            : MemoryCoreBlock::TYPE_OPERATIONAL;
    }

    /**
     * Check if this is an identity block.
     */
    public function isIdentityBlock(): bool
    {
        return in_array($this->route('key'), self::IDENTITY_BLOCK_KEYS, true);
    }
}
