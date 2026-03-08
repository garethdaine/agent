<?php

declare(strict_types=1);

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
        $maxTextLength = max(1, (int) config('agent.interrogation.max_text_length', 60000));

        return match ((string) $this->route('key')) {
            'interrogation.max_active_sessions' => [
                'value' => ['required', 'integer', 'between:1,50'],
            ],
            'interrogation.default_runner' => [
                'value' => ['required', Rule::in(['claude', 'codex'])],
            ],
            'interrogation.system_prompt' => [
                'value' => ['present', 'string', 'max:'.$maxTextLength],
            ],
            default => [
                'value' => ['required'],
            ],
        };
    }
}
