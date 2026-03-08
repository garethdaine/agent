<?php

declare(strict_types=1);

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:120'],
            'value' => ['nullable'],
        ];
    }
}
