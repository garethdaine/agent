<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\RepoAnalysis;

use App\Support\Agent\PathPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRepoAnalysisSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name')) {
            $name = $this->input('name');
            $normalized['name'] = is_string($name) ? trim($name) : $name;
        }

        if ($this->has('analyzer_profile')) {
            $profile = $this->input('analyzer_profile');
            $normalized['analyzer_profile'] = is_string($profile) ? trim($profile) : $profile;
        }

        if ($this->has('runner_type')) {
            $runnerType = $this->input('runner_type');
            $normalized['runner_type'] = is_string($runnerType) ? trim($runnerType) : $runnerType;
        }

        if ($this->has('project_directory')) {
            $projectDirectory = $this->input('project_directory');
            $normalized['project_directory'] = is_string($projectDirectory) ? trim($projectDirectory) : $projectDirectory;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'analyzer_profile' => ['nullable', 'string', 'max:64'],
            'runner_type' => ['nullable', 'string', 'in:claude,codex'],
            'project_directory' => ['sometimes', 'required', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('project_directory')) {
                return;
            }

            $pathPolicy = app(PathPolicy::class);
            $projectDirectoryError = $pathPolicy->validateWorkingDirectory((string) $this->input('project_directory'));

            if ($projectDirectoryError !== null) {
                $validator->errors()->add('project_directory', $projectDirectoryError);
            }
        });
    }
}
