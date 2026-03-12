<?php

declare(strict_types=1);

namespace App\Http\Requests\Credentials;

use App\Models\CredentialVault;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'max:64'],
            'key' => ['sometimes', 'string', 'max:128'],
            'url' => ['nullable', 'string', 'max:2048'],
            'secrets' => ['sometimes', 'array'],
            'secrets.*' => ['nullable', 'string', 'max:65535'],
            'metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'approval_mode' => ['sometimes', 'string', Rule::in(CredentialVault::VALID_APPROVAL_MODES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
