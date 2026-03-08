<?php

namespace App\Http\Requests\Connectors;

use Illuminate\Foundation\Http\FormRequest;

class ListConnectorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string'],
            'industry' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:available,deprecated'],
        ];
    }
}
