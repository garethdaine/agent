<?php

namespace App\Http\Requests\Connectors;

use Illuminate\Foundation\Http\FormRequest;

class ConnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'alias' => ['nullable', 'string', 'max:128'],
        ];
    }
}
