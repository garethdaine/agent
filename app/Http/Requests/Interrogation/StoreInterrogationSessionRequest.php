<?php

declare(strict_types=1);

namespace App\Http\Requests\Interrogation;

use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;
use App\Support\Agent\PathPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInterrogationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InterrogationSession::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $projectDirectory = $this->input('project_directory');

        $this->merge([
            'project_directory' => is_string($projectDirectory) ? trim($projectDirectory) : $projectDirectory,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxTextLength = max(1, (int) config('agent.interrogation.max_text_length', 60000));

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'runner_type' => ['required', Rule::in(['claude', 'codex'])],
            'model' => ['nullable', 'string', 'max:128'],
            'project_directory' => ['required', 'string', 'max:1024'],
            'interrogation_type' => ['required', Rule::in([InterrogationSession::TYPE_FEATURE, InterrogationSession::TYPE_GENERAL])],
            'feature_brief' => ['nullable', 'string', 'max:'.$maxTextLength, 'required_if:interrogation_type,'.InterrogationSession::TYPE_FEATURE],

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
            $pathPolicy = app(PathPolicy::class);

            $projectDirectoryError = $pathPolicy->validateWorkingDirectory((string) $this->input('project_directory'));

            if ($projectDirectoryError !== null) {
                $validator->errors()->add('project_directory', $projectDirectoryError);
            }

            if ($this->user() !== null) {
                $maxActiveSessions = $this->resolveMaxActiveSessions((int) $this->user()->id);
                $activeCount = InterrogationSession::query()
                    ->forUser((int) $this->user()->id)
                    ->active()
                    ->count();

                if ($activeCount >= $maxActiveSessions) {
                    $validator->errors()->add('runner_type', sprintf('You already have %d active interrogation sessions.', $maxActiveSessions));
                }
            }

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

    private function resolveMaxActiveSessions(int $userId): int
    {
        $default = max(1, (int) config('agent.interrogation.max_active_sessions', 3));
        $setting = InterrogationSetting::getForUser($userId, 'interrogation.max_active_sessions', $default);

        if (is_numeric($setting)) {
            return max(1, min(50, (int) $setting));
        }

        return $default;
    }
}
