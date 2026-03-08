<?php

declare(strict_types=1);

namespace App\Support\Org;

use App\Events\Office\AgentActivityChanged;
use App\Events\Org\OrgCouncilTemplateArchived;
use App\Events\Org\OrgCouncilTemplateCreated;
use App\Events\Org\OrgCouncilTemplateRestored;
use App\Events\Org\OrgCouncilTemplateUpdated;
use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\User;
use App\Support\Org\Synthesis\ChairDecidesSynthesisStrategy;
use App\Support\Org\Synthesis\MajoritySynthesisStrategy;
use App\Support\Org\Synthesis\SynthesisStrategyInterface;
use App\Support\Org\Synthesis\WeightedSynthesisStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for managing council templates and executing synthesis.
 *
 * Councils are groups of org agents that collectively review evidence and
 * reach decisions through deterministic synthesis rules. Model-mediated
 * synthesis is opt-in only.
 */
class OrgCouncilService
{
    /** @var array<string, SynthesisStrategyInterface> */
    private array $strategies = [];

    public function __construct(
        private readonly OrgAuditService $auditService,
    ) {
        // Register deterministic synthesis strategies
        $this->strategies['majority'] = new MajoritySynthesisStrategy;
        $this->strategies['weighted'] = new WeightedSynthesisStrategy;
        $this->strategies['chair_decides'] = new ChairDecidesSynthesisStrategy;
    }

    /**
     * Create a new council template.
     *
     * @throws InvalidArgumentException if member agents don't belong to user
     */
    public function create(User $user, array $data): OrgCouncilTemplate
    {
        $this->validateMemberOwnership($user, $data['member_list'] ?? []);
        $this->validateSynthesisMode($data['synthesis_mode'] ?? 'majority');

        $template = OrgCouncilTemplate::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'member_list' => $data['member_list'] ?? [],
            'evidence_payload_schema' => $data['evidence_payload_schema'] ?? null,
            'member_response_schema' => $data['member_response_schema'] ?? null,
            'synthesis_mode' => $data['synthesis_mode'] ?? 'majority',
            'use_model_synthesis' => $data['use_model_synthesis'] ?? false,
            'report_sections' => $data['report_sections'] ?? ['agreements', 'conflicts', 'recommended_actions'],
        ]);

        $correlationId = $this->generateCorrelationId();

        $this->auditService->logMutation(
            action: 'created',
            resource: $template,
            before: null,
            after: $template->toArray(),
            actor: $user,
            correlationId: $correlationId,
        );

        OrgCouncilTemplateCreated::dispatch($template, $correlationId);

        return $template;
    }

    /**
     * Update an existing council template.
     *
     * @throws InvalidArgumentException if member agents don't belong to user
     */
    public function update(OrgCouncilTemplate $template, array $data): OrgCouncilTemplate
    {
        if (isset($data['member_list'])) {
            $this->validateMemberOwnership($template->user, $data['member_list']);
        }

        if (isset($data['synthesis_mode'])) {
            $this->validateSynthesisMode($data['synthesis_mode']);
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
            correlationId: $correlationId,
        );

        OrgCouncilTemplateUpdated::dispatch($template, $before, $after, $correlationId);

        return $template;
    }

    /**
     * Archive (soft delete) a council template.
     */
    public function archive(OrgCouncilTemplate $template): void
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
            correlationId: $correlationId,
        );

        OrgCouncilTemplateArchived::dispatch($template, $correlationId);
    }

    /**
     * Restore an archived council template.
     */
    public function restore(OrgCouncilTemplate $template): void
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
            correlationId: $correlationId,
        );

        OrgCouncilTemplateRestored::dispatch($template, $correlationId);
    }

    /**
     * Synthesize council member responses into a single decision.
     *
     * Uses deterministic synthesis by default. Model-mediated synthesis
     * is only used when explicitly enabled via use_model_synthesis flag.
     *
     * @param  Collection|array  $responses  Member responses containing decision field
     * @param  string  $decisionField  The key containing the decision value (default: 'decision')
     */
    public function synthesize(
        OrgCouncilTemplate $template,
        Collection|array $responses,
        string $decisionField = 'decision',
    ): SynthesisResult {
        $responsesArray = $responses instanceof Collection ? $responses->all() : $responses;
        $memberList = $template->member_list ?? [];
        $mode = $template->synthesis_mode ?? 'majority';

        $memberIds = array_column($memberList, 'agent_id');

        $this->broadcastActivity($template->user_id, 'council.deliberation_started', [
            'council_name' => $template->name,
            'member_ids' => $memberIds,
            'synthesis_mode' => $mode,
        ]);

        $strategy = $this->getStrategy($mode);
        $result = $strategy->synthesize($responsesArray, $decisionField, $memberList);

        $this->broadcastActivity($template->user_id, 'council.deliberation_ended', [
            'council_name' => $template->name,
            'member_ids' => $memberIds,
            'outcome' => $result->decision ?? null,
        ]);

        return $result;
    }

    /**
     * Get the synthesis strategy for the given mode.
     *
     * @throws InvalidArgumentException if mode is not supported
     */
    public function getStrategy(string $mode): SynthesisStrategyInterface
    {
        if (! isset($this->strategies[$mode])) {
            throw new InvalidArgumentException("Unsupported synthesis mode: {$mode}");
        }

        return $this->strategies[$mode];
    }

    /**
     * Validate that all member agents belong to the user.
     *
     * @throws InvalidArgumentException if any agent doesn't belong to user
     */
    private function validateMemberOwnership(User $user, array $memberList): void
    {
        $agentIds = array_column($memberList, 'agent_id');

        if (empty($agentIds)) {
            return; // Empty member list is valid
        }

        $ownedCount = OrgAgentProfile::where('user_id', $user->id)
            ->whereIn('id', $agentIds)
            ->count();

        if ($ownedCount !== count($agentIds)) {
            throw new InvalidArgumentException('All member agents must belong to the user');
        }
    }

    /**
     * Validate that the synthesis mode is supported.
     *
     * @throws InvalidArgumentException if mode is not supported
     */
    private function validateSynthesisMode(string $mode): void
    {
        if (! in_array($mode, ['majority', 'weighted', 'chair_decides'], true)) {
            throw new InvalidArgumentException("Unsupported synthesis mode: {$mode}");
        }
    }

    /**
     * Generate a correlation ID for tracking operations.
     */
    private function generateCorrelationId(): string
    {
        return Str::uuid()->toString();
    }

    private function broadcastActivity(int|string $userId, string $eventType, array $payload): void
    {
        try {
            AgentActivityChanged::dispatch((int) $userId, $eventType, $payload);
        } catch (\Throwable) {
            // Best-effort
        }
    }
}
