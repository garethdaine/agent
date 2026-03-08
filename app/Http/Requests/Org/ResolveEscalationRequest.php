<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Models\OrgEscalation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveEscalationRequest extends FormRequest
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
            'resolution' => [
                'required',
                'string',
                Rule::in([OrgEscalation::STATE_APPROVED, OrgEscalation::STATE_REJECTED]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution.in' => 'Resolution must be either "approved" or "rejected".',
        ];
    }
}
