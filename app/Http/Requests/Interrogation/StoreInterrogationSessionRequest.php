<?php

namespace App\Http\Requests\Interrogation;

use App\Models\InterrogationSession;
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
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'runner_type' => ['required', Rule::in(['claude', 'codex'])],
            'project_directory' => ['required', 'string', 'max:1024'],
            'interrogation_type' => ['required', Rule::in([InterrogationSession::TYPE_FEATURE, InterrogationSession::TYPE_GENERAL])],
            'feature_brief' => ['nullable', 'string', 'max:50000', 'required_if:interrogation_type,'.InterrogationSession::TYPE_FEATURE],
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
                $activeCount = InterrogationSession::query()
                    ->forUser((int) $this->user()->id)
                    ->active()
                    ->count();

                if ($activeCount >= 3) {
                    $validator->errors()->add('runner_type', 'You already have 3 active interrogation sessions.');
                }
            }
        });
    }
}
