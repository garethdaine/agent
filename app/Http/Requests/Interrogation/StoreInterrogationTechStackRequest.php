<?php

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterrogationTechStackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'documentation_url' => is_string($this->input('documentation_url'))
                ? trim((string) $this->input('documentation_url'))
                : $this->input('documentation_url'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'documentation_url' => ['required', 'url:http,https', 'max:2048'],
        ];
    }
}
