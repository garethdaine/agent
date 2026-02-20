<?php

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
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'feature_brief' => ['sometimes', 'nullable', 'string', 'max:50000'],
        ];
    }
}
