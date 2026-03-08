<?php

declare(strict_types=1);

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
