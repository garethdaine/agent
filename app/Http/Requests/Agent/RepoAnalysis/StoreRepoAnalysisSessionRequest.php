<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use App\Support\Agent\PathPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRepoAnalysisSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RepoAnalysisSession::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $projectDirectory = $this->input('project_directory');
        $name = $this->input('name');
        $profile = $this->input('analyzer_profile');

        $this->merge([
            'project_directory' => is_string($projectDirectory) ? trim($projectDirectory) : $projectDirectory,
            'name' => is_string($name) ? trim($name) : $name,
            'analyzer_profile' => is_string($profile) ? trim($profile) : $profile,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'project_directory' => ['required', 'string', 'max:1024'],
            'analyzer_profile' => ['nullable', 'string', 'max:64'],
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

            $user = $this->user();
            if ($user === null) {
                return;
            }

            $maxActiveSessions = max(1, (int) config('repo_analysis.user.max_active_sessions_per_user', 2));
            $activeSessions = RepoAnalysisSession::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', [
                    'completed',
                    'failed',
                ])
                ->count();

            if ($activeSessions >= $maxActiveSessions) {
                $validator->errors()->add(
                    'project_directory',
                    sprintf('You already have %d active repo analysis sessions.', $maxActiveSessions)
                );
            }
        });
    }
}
