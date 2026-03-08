<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Models\DelegateeProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrgAgentRequest extends FormRequest
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
            'role_slug' => ['required', 'string', 'min:2', 'max:100'],
            'role_description' => ['required', 'string', 'max:2000'],
            'delegatee_profile_id' => ['required', 'integer', 'exists:delegatee_profiles,id'],
            'capability_bindings' => ['present', 'array'],
            'capability_bindings.*' => ['string'],
            'authority_overrides' => ['present', 'array'],
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

            $delegateeId = $this->input('delegatee_profile_id');
            $delegatee = DelegateeProfile::find($delegateeId);

            if ($delegatee === null) {
                $validator->errors()->add('delegatee_profile_id', 'Delegatee profile not found.');

                return;
            }

            // Validate delegatee belongs to the authenticated user
            if ($delegatee->user_id !== $this->user()->id) {
                $validator->errors()->add(
                    'delegatee_profile_id',
                    'The delegatee profile must belong to you.'
                );

                return;
            }

            // Validate authority narrowing - capability_bindings must be a subset of delegatee's capabilities
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
        });
    }
}
