<?php

namespace App\Http\Requests\Org;

use App\Models\DelegateeProfile;
use App\Models\OrgAgentProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrgAgentRequest extends FormRequest
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
            'role_slug' => ['sometimes', 'string', 'min:2', 'max:100'],
            'role_description' => ['sometimes', 'string', 'max:2000'],
            'delegatee_profile_id' => ['sometimes', 'integer', 'exists:delegatee_profiles,id'],
            'capability_bindings' => ['sometimes', 'array'],
            'capability_bindings.*' => ['string'],
            'authority_overrides' => ['sometimes', 'array'],
            'default_output_schema' => ['nullable', 'array'],
            'parent_agent_id' => ['nullable', 'uuid', 'exists:org_agent_profiles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Get the current profile being updated
            /** @var OrgAgentProfile|null $profile */
            $profile = OrgAgentProfile::find($this->route('id'));

            if ($profile === null) {
                return; // Controller will handle 404
            }

            // Determine which delegatee to validate against
            $delegateeId = $this->input('delegatee_profile_id', $profile->delegatee_profile_id);
            $delegatee = DelegateeProfile::find($delegateeId);

            if ($delegatee === null) {
                $validator->errors()->add('delegatee_profile_id', 'Delegatee profile not found.');

                return;
            }

            // Validate delegatee belongs to the authenticated user
            if ($this->has('delegatee_profile_id') && $delegatee->user_id !== $this->user()->id) {
                $validator->errors()->add(
                    'delegatee_profile_id',
                    'The delegatee profile must belong to you.'
                );

                return;
            }

            // Validate authority narrowing if capability_bindings is being updated
            if ($this->has('capability_bindings')) {
                $requestedCapabilities = $this->input('capability_bindings', []);
                if (! empty($requestedCapabilities)) {
                    $delegateeCapabilitySlugs = $delegatee->capabilities()->pluck('slug')->toArray();
                    $widening = array_diff($requestedCapabilities, $delegateeCapabilitySlugs);

                    if (! empty($widening)) {
                        $validator->errors()->add(
                            'capability_bindings',
                            'Cannot request capabilities not present in delegatee profile: '.implode(', ', $widening)
                        );
                    }
                }
            }
        });
    }
}
