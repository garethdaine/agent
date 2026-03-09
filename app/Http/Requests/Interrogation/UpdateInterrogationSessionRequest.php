<?php

declare(strict_types=1);

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInterrogationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $featureBrief = $this->input('feature_brief');

        if (is_string($name)) {
            $trimmedName = trim($name);
            $this->merge([
                'name' => $trimmedName === '' ? null : $trimmedName,
            ]);
        }

        if (is_string($featureBrief)) {
            $trimmedBrief = trim($featureBrief);
            $this->merge([
                'feature_brief' => $trimmedBrief === '' ? null : $trimmedBrief,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxTextLength = max(1, (int) config('agent.interrogation.max_text_length', 60000));

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'feature_brief' => ['sometimes', 'nullable', 'string', 'max:'.$maxTextLength],
            'model' => ['sometimes', 'nullable', 'string', 'max:128'],

            'git' => ['sometimes', 'nullable', 'array'],
            'git.commit_enabled' => ['sometimes', 'boolean'],
            'git.conventional_commits' => ['sometimes', 'boolean'],
            'git.worktree_enabled' => ['sometimes', 'boolean'],
            'git.branching_enabled' => ['sometimes', 'boolean'],
            'git.branch_prefix' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\/_\-\.]*$/'],
            'git.target_branch' => ['sometimes', 'nullable', 'string', 'max:255'],

            'build_settings' => ['sometimes', 'nullable', 'array'],
            'build_settings.auto_advance_tasks' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $git = (array) ($this->input('git') ?? []);
            if ($git !== []) {
                $branchingEnabled = (bool) ($git['branching_enabled'] ?? false);
                $targetBranch = $git['target_branch'] ?? null;

                if ($branchingEnabled && is_string($targetBranch) && trim($targetBranch) !== '') {
                    $validator->errors()->add('git.target_branch', 'Target branch is only used when branching is disabled (trunk-based mode).');
                }
            }
        });
    }
}
