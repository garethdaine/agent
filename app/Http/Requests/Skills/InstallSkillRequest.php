<?php

declare(strict_types=1);

namespace App\Http\Requests\Skills;

use Illuminate\Foundation\Http\FormRequest;

class InstallSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'url' => ['sometimes', 'url'],
        ];
    }
}
