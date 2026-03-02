<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use App\Models\AgentJobRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for appending to working memory.
 */
class AppendWorkingMemoryRequest extends FormRequest
{
    /**
     * Valid roles for working memory entries.
     *
     * @var array<string>
     */
    public const VALID_ROLES = [
        'user',
        'assistant',
        'system',
        'tool',
        'tool_call',
        'stderr',
        'lifecycle',
    ];

    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        // Validate that the run belongs to the authenticated user
        $runId = $this->input('run_id');
        if (! $runId) {
            return true; // Let validation handle missing run_id
        }

        $run = AgentJobRun::with('job')->find($runId);

        return $run && $run->job && $run->job->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'run_id' => ['required', 'integer', 'exists:agent_job_runs,id'],
            'role' => ['required', 'string', Rule::in(self::VALID_ROLES)],
            'content' => ['required', 'string'],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get metadata array from input.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->input('metadata', []);
    }
}
