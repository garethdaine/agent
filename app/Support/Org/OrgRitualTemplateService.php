<?php

declare(strict_types=1);

namespace App\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use App\Support\Org\Exceptions\InvalidCronExpressionException;
use App\Support\Org\Exceptions\InvalidPhaseRoleMappingException;
use Cron\CronExpression;
use Illuminate\Support\Str;

class OrgRitualTemplateService
{
    public function __construct(
        private readonly PhaseGraphValidator $phaseGraphValidator,
        private readonly OrgAuditService $auditService,
    ) {}

    /**
     * Create a new ritual template with validation.
     *
     * @throws InvalidCronExpressionException
     * @throws InvalidPhaseRoleMappingException
     * @throws Exceptions\InvalidPhaseGraphException
     * @throws Exceptions\ConditionalBranchingNotAllowedException
     */
    public function create(User $user, array $data): OrgRitualTemplate
    {
        $this->validateCronExpression($data['cron_expression'] ?? '');
        $this->phaseGraphValidator->validate($data['phase_graph'] ?? []);
        $this->validatePhaseRoleMappings(
            $user,
            $data['phase_graph'] ?? [],
            $data['phase_role_mappings'] ?? []
        );

        $template = OrgRitualTemplate::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'cron_expression' => $data['cron_expression'],
            'timezone' => $data['timezone'] ?? 'UTC',
            'nl_source_metadata' => $data['nl_source_metadata'] ?? null,
            'phase_graph' => $data['phase_graph'] ?? [],
            'phase_role_mappings' => $data['phase_role_mappings'] ?? [],
            'context_inputs' => $data['context_inputs'] ?? null,
            'verification_strategy' => $data['verification_strategy'] ?? null,
            'delivery_targets' => $data['delivery_targets'] ?? null,
            'escalation_timeout_seconds' => $data['escalation_timeout_seconds'] ?? 3600,
            'notification_level' => $data['notification_level'] ?? OrgRitualTemplate::NOTIFICATION_LEVEL_ESCALATIONS_ONLY,
            'is_paused' => $data['is_paused'] ?? false,
        ]);

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'created',
            resource: $template,
            before: null,
            after: $template->toArray(),
            actor: $user,
            correlationId: $correlationId
        );

        return $template;
    }

    /**
     * Update an existing ritual template with re-validation.
     *
     * @throws InvalidCronExpressionException
     * @throws InvalidPhaseRoleMappingException
     * @throws Exceptions\InvalidPhaseGraphException
     * @throws Exceptions\ConditionalBranchingNotAllowedException
     */
    public function update(OrgRitualTemplate $template, array $data): OrgRitualTemplate
    {
        if (isset($data['cron_expression'])) {
            $this->validateCronExpression($data['cron_expression']);
        }

        if (isset($data['phase_graph'])) {
            $this->phaseGraphValidator->validate($data['phase_graph']);

            // Re-validate mappings if graph changed
            $this->validatePhaseRoleMappings(
                $template->user,
                $data['phase_graph'],
                $data['phase_role_mappings'] ?? $template->phase_role_mappings ?? []
            );
        } elseif (isset($data['phase_role_mappings'])) {
            // Validate new mappings against existing graph
            $this->validatePhaseRoleMappings(
                $template->user,
                $template->phase_graph ?? [],
                $data['phase_role_mappings']
            );
        }

        $before = $template->toArray();

        $template->update($data);
        $template = $template->fresh();

        $after = $template->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'updated',
            resource: $template,
            before: $before,
            after: $after,
            actor: $template->user,
            correlationId: $correlationId
        );

        return $template;
    }

    /**
     * Archive (soft delete) a ritual template.
     */
    public function archive(OrgRitualTemplate $template): void
    {
        $before = $template->toArray();

        $template->update(['archived_at' => now()]);
        $template = $template->fresh();

        $after = $template->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'archived',
            resource: $template,
            before: $before,
            after: $after,
            actor: $template->user,
            correlationId: $correlationId
        );
    }

    /**
     * Restore an archived ritual template.
     */
    public function restore(OrgRitualTemplate $template): void
    {
        $before = $template->toArray();

        $template->update(['archived_at' => null]);
        $template = $template->fresh();

        $after = $template->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'restored',
            resource: $template,
            before: $before,
            after: $after,
            actor: $template->user,
            correlationId: $correlationId
        );
    }

    /**
     * Pause a ritual template's schedule.
     * This prevents new runs from being scheduled but does not affect in-flight runs.
     */
    public function pause(OrgRitualTemplate $template): void
    {
        $before = $template->toArray();

        $template->update(['is_paused' => true]);
        $template = $template->fresh();

        $after = $template->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'paused',
            resource: $template,
            before: $before,
            after: $after,
            actor: $template->user,
            correlationId: $correlationId
        );
    }

    /**
     * Resume a paused ritual template's schedule.
     */
    public function resume(OrgRitualTemplate $template): void
    {
        $before = $template->toArray();

        $template->update(['is_paused' => false]);
        $template = $template->fresh();

        $after = $template->toArray();

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'resumed',
            resource: $template,
            before: $before,
            after: $after,
            actor: $template->user,
            correlationId: $correlationId
        );
    }

    /**
     * Validate a cron expression using the dragonmantank/cron-expression package.
     *
     * @throws InvalidCronExpressionException
     */
    private function validateCronExpression(string $expression): void
    {
        if (empty($expression)) {
            throw new InvalidCronExpressionException(
                'Cron expression cannot be empty',
                $expression
            );
        }

        if (! CronExpression::isValidExpression($expression)) {
            throw new InvalidCronExpressionException(
                "Invalid cron expression: '{$expression}'",
                $expression
            );
        }
    }

    /**
     * Validate that all phase role mappings reference existing org agent roles for the user.
     *
     * @throws InvalidPhaseRoleMappingException
     */
    private function validatePhaseRoleMappings(User $user, array $phaseGraph, array $mappings): void
    {
        // Get all active role slugs for this user
        $userRoleSlugs = OrgAgentProfile::query()
            ->forUser($user->id)
            ->active()
            ->pluck('role_slug')
            ->unique()
            ->toArray();

        // Extract all phase IDs from the graph
        $phaseIds = collect($phaseGraph)->pluck('id')->toArray();

        // Validate each mapping
        foreach ($mappings as $phaseId => $roleSlug) {
            // Validate phase exists in graph
            if (! in_array($phaseId, $phaseIds, true)) {
                throw new InvalidPhaseRoleMappingException(
                    "Phase '{$phaseId}' does not exist in the phase graph",
                    $phaseId,
                    $roleSlug
                );
            }

            // Validate role exists for user
            if (! in_array($roleSlug, $userRoleSlugs, true)) {
                throw new InvalidPhaseRoleMappingException(
                    "Role '{$roleSlug}' does not exist for user's org agents",
                    $phaseId,
                    $roleSlug
                );
            }
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
