<?php

declare(strict_types=1);

namespace App\Http\Requests\Docs;

use Illuminate\Foundation\Http\FormRequest;

class SearchDocsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if (is_string($this->query('q'))) {
            $input['q'] = trim($this->query('q'));
        }

        if (is_string($this->query('domain'))) {
            $input['domain'] = trim($this->query('domain'));
        }

        if (is_string($this->query('section'))) {
            $input['section'] = trim($this->query('section'));
        }

        if (is_string($this->query('route'))) {
            $input['route'] = trim($this->query('route'));
        }

        $this->merge($input);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:160'],
            'domain' => ['nullable', 'string', 'in:product_doc,api_doc,tooltip'],
            'section' => ['nullable', 'string', 'max:80'],
            'route' => ['nullable', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
