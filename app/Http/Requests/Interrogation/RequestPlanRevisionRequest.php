<?php

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPlanRevisionRequest extends FormRequest
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
            'action' => ['required', Rule::in(['expand', 'simplify', 'add_examples', 'rewrite', 'split_into_steps', 'add_acceptance_criteria'])],
            'section' => ['nullable', 'string', 'max:255'],
            'sections' => ['nullable', 'array', 'max:50'],
            'sections.*' => ['string', 'max:255', 'distinct'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
