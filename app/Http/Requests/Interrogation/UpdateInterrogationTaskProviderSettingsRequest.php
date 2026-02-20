<?php

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInterrogationTaskProviderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $projectMode = $this->input('project_mode');
        $existingProjectId = $this->input('existing_project_id');

        if (is_string($projectMode)) {
            $this->merge([
                'project_mode' => strtolower(trim($projectMode)),
            ]);
        }

        if (is_string($existingProjectId)) {
            $trimmed = trim($existingProjectId);
            $this->merge([
                'existing_project_id' => $trimmed === '' ? null : $trimmed,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_mode' => ['required', 'string', Rule::in(['create_new', 'existing'])],
            'existing_project_id' => [
                Rule::requiredIf(fn (): bool => $this->input('project_mode') === 'existing'),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
