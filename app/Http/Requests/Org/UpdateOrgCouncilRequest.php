<?php

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrgCouncilRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'member_list' => ['sometimes', 'array', 'min:1'],
            'member_list.*.agent_id' => ['required_with:member_list', 'string', 'uuid'],
            'member_list.*.perspective' => ['required_with:member_list', 'string', 'max:100'],
            'member_list.*.weight' => ['sometimes', 'numeric', 'min:0.1', 'max:100'],
            'member_list.*.is_chair' => ['sometimes', 'boolean'],
            'evidence_payload_schema' => ['nullable', 'array'],
            'member_response_schema' => ['nullable', 'array'],
            'synthesis_mode' => ['sometimes', 'string', 'in:majority,weighted,chair_decides'],
            'use_model_synthesis' => ['sometimes', 'boolean'],
            'report_sections' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Only validate if both member_list and synthesis_mode are being updated,
            // or if only one is updated we need to consider the existing template values
            $memberList = $this->input('member_list');
            $synthesisMode = $this->input('synthesis_mode');

            if ($memberList === null && $synthesisMode === null) {
                return;
            }

            // If we need to validate, we need to consider the template being updated
            // This validation will be handled by the service layer where we have access
            // to the existing template data
        });
    }
}
