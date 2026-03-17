<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkflowInterrogator;

use App\Models\WorkflowInterrogationSession;
use App\Support\Agent\PathPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkflowInterrogationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $projectDirectory = $this->input('project_directory');

        $this->merge([
            'project_directory' => is_string($projectDirectory) ? trim($projectDirectory) : $projectDirectory,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'runner_type' => ['required', Rule::in(['claude', 'codex', 'custom'])],
            'model' => ['nullable', 'string', 'max:120'],
            'project_directory' => ['required', 'string', 'max:1024'],
            'interrogation_mode' => ['required', Rule::in([
                WorkflowInterrogationSession::MODE_WORKFLOW,
                WorkflowInterrogationSession::MODE_GENERAL,
            ])],
            'company_name' => ['required', 'string', 'max:255'],
            'company_description' => ['nullable', 'string', 'max:20000'],
            'workflow_title' => ['required', 'string', 'max:255'],
            'workflow_brief' => ['required', 'string', 'max:60000'],
            'target_teams' => ['nullable', 'array', 'max:20'],
            'target_teams.*' => ['string', 'max:255'],
            'systems' => ['nullable', 'array', 'max:30'],
            'systems.*' => ['string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:txt,md,markdown,csv,json,yaml,yml,png,jpg,jpeg,webp,gif,pdf'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $projectDirectoryError = app(PathPolicy::class)->validateWorkingDirectory((string) $this->input('project_directory'));

            if ($projectDirectoryError !== null) {
                $validator->errors()->add('project_directory', $projectDirectoryError);
            }

            $runnerType = (string) $this->input('runner_type');
            $model = trim((string) $this->input('model', ''));

            if ($runnerType === 'custom' && $model !== '') {
                $validator->errors()->add('model', 'Custom runner sessions do not use a model selection.');
            }
        });
    }
}
