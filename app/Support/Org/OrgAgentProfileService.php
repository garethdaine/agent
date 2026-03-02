<?php

namespace App\Support\Org;

use App\Events\Org\OrgAgentProfileArchived;
use App\Events\Org\OrgAgentProfileCreated;
use App\Events\Org\OrgAgentProfileRestored;
use App\Events\Org\OrgAgentProfileUpdated;
use App\Models\DelegateeProfile;
use App\Models\OrgAgentProfile;
use App\Models\User;
use App\Support\Org\Exceptions\AuthorityWideningException;
use App\Support\Org\Exceptions\InvalidDelegateeBindingException;
use Illuminate\Support\Str;

class OrgAgentProfileService
{
    public function __construct(
        private readonly OrgAuditService $auditService
    ) {}

    /**
     * Create a new org agent profile with validated delegatee binding.
     *
     * @throws InvalidDelegateeBindingException
     * @throws AuthorityWideningException
     */
    public function create(User $user, array $data): OrgAgentProfile
    {
        $delegatee = DelegateeProfile::find($data['delegatee_profile_id']);

        $this->validateDelegateeOwnership($user, $delegatee);
        $this->validateAuthorityNarrowing($delegatee, $data['capability_bindings'] ?? []);

        $profile = OrgAgentProfile::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'role_slug' => $data['role_slug'],
            'role_description' => $data['role_description'],
            'delegatee_profile_id' => $delegatee->id,
            'capability_bindings' => $data['capability_bindings'] ?? [],
            'authority_overrides' => $data['authority_overrides'] ?? [],
            'default_output_schema' => $data['default_output_schema'] ?? null,
            'parent_agent_id' => $data['parent_agent_id'] ?? null,
        ]);

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'created',
            resource: $profile,
            before: null,
            after: $profile->toArray(),
            actor: $user,
            correlationId: $correlationId
        );

        OrgAgentProfileCreated::dispatch($profile, $correlationId);

        return $profile;
    }

    /**
     * Update an existing org agent profile with re-validation.
     *
     * @throws InvalidDelegateeBindingException
     * @throws AuthorityWideningException
     */
    public function update(OrgAgentProfile $profile, array $data): OrgAgentProfile
    {
        if (isset($data['delegatee_profile_id'])) {
            $delegatee = DelegateeProfile::find($data['delegatee_profile_id']);
            $this->validateDelegateeOwnership($profile->user, $delegatee);
            $this->validateAuthorityNarrowing(
                $delegatee,
                $data['capability_bindings'] ?? $profile->capability_bindings
            );
        } elseif (isset($data['capability_bindings'])) {
            $this->validateAuthorityNarrowing(
                $profile->delegateeProfile,
                $data['capability_bindings']
            );
        }

        $before = $profile->toArray();

        $profile->update($data);
        $profile = $profile->fresh();

        $after = $profile->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'updated',
            resource: $profile,
            before: $before,
            after: $after,
            actor: $profile->user,
            correlationId: $correlationId
        );

        OrgAgentProfileUpdated::dispatch($profile, $before, $after, $correlationId);

        return $profile;
    }

    /**
     * Archive (soft delete) an org agent profile.
     */
    public function archive(OrgAgentProfile $profile): void
    {
        $before = $profile->toArray();

        $profile->update(['archived_at' => now()]);
        $profile = $profile->fresh();

        $after = $profile->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'archived',
            resource: $profile,
            before: $before,
            after: $after,
            actor: $profile->user,
            correlationId: $correlationId
        );

        OrgAgentProfileArchived::dispatch($profile, $correlationId);
    }

    /**
     * Restore an archived org agent profile.
     */
    public function restore(OrgAgentProfile $profile): void
    {
        $before = $profile->toArray();

        $profile->update(['archived_at' => null]);
        $profile = $profile->fresh();

        $after = $profile->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'restored',
            resource: $profile,
            before: $before,
            after: $after,
            actor: $profile->user,
            correlationId: $correlationId
        );

        OrgAgentProfileRestored::dispatch($profile, $correlationId);
    }

    /**
     * Validate that the delegatee profile belongs to the user.
     *
     * @throws InvalidDelegateeBindingException
     */
    private function validateDelegateeOwnership(User $user, ?DelegateeProfile $delegatee): void
    {
        if (! $delegatee || $delegatee->user_id !== $user->id) {
            throw new InvalidDelegateeBindingException(
                'Delegatee profile must belong to the user'
            );
        }
    }

    /**
     * Validate that requested capabilities are a subset of delegatee capabilities.
     *
     * This enforces authority narrowing - org agents cannot request capabilities
     * that their underlying delegatee profile does not have.
     *
     * @throws AuthorityWideningException
     */
    private function validateAuthorityNarrowing(DelegateeProfile $delegatee, array $requestedCapabilities): void
    {
        // Get the capability slugs from the delegatee's capabilities relationship
        $delegateeCapabilitySlugs = $delegatee->capabilities()->pluck('slug')->toArray();

        $widening = array_diff($requestedCapabilities, $delegateeCapabilitySlugs);

        if (! empty($widening)) {
            throw new AuthorityWideningException(
                'Cannot request capabilities not present in delegatee profile: '.implode(', ', $widening),
                $widening
            );
        }
    }

    /**
     * Generate a correlation ID for tracking operations.
     */
    private function generateCorrelationId(): string
    {
        return Str::uuid()->toString();
    }
}
