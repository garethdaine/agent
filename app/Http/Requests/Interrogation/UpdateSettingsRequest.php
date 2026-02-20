<?php

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ((string) $this->route('key') === 'interrogation.system_prompt' && $this->input('value') === null) {
            $this->merge(['value' => '']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ((string) $this->route('key')) {
            'interrogation.max_active_sessions' => [
                'value' => ['required', 'integer', 'between:1,50'],
            ],
            'interrogation.default_runner' => [
                'value' => ['required', Rule::in(['claude', 'codex'])],
            ],
            'interrogation.system_prompt' => [
                'value' => ['present', 'string', 'max:50000'],
            ],
            default => [
                'value' => ['required'],
            ],
        };
    }
}
