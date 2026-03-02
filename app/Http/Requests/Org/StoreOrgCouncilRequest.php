<?php

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrgCouncilRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'member_list' => ['required', 'array', 'min:1'],
            'member_list.*.agent_id' => ['required', 'string', 'uuid'],
            'member_list.*.perspective' => ['required', 'string', 'max:100'],
            'member_list.*.weight' => ['sometimes', 'numeric', 'min:0.1', 'max:100'],
            'member_list.*.is_chair' => ['sometimes', 'boolean'],
            'evidence_payload_schema' => ['nullable', 'array'],
            'member_response_schema' => ['nullable', 'array'],
            'synthesis_mode' => ['required', 'string', 'in:majority,weighted,chair_decides'],
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

            $memberList = $this->input('member_list', []);
            $synthesisMode = $this->input('synthesis_mode');

            // Validate chair_decides has exactly one chair
            if ($synthesisMode === 'chair_decides') {
                $chairCount = 0;
                foreach ($memberList as $member) {
                    if (! empty($member['is_chair'])) {
                        $chairCount++;
                    }
                }

                if ($chairCount !== 1) {
                    $validator->errors()->add(
                        'member_list',
                        'Chair decides synthesis mode requires exactly one member marked as chair (is_chair: true).'
                    );
                }
            }

            // Validate weighted mode has weights
            if ($synthesisMode === 'weighted') {
                $hasWeights = false;
                foreach ($memberList as $member) {
                    if (isset($member['weight'])) {
                        $hasWeights = true;
                        break;
                    }
                }

                if (! $hasWeights) {
                    $validator->errors()->add(
                        'member_list',
                        'Weighted synthesis mode requires at least one member with a weight specified.'
                    );
                }
            }
        });
    }
}
