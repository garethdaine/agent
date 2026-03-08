<?php

declare(strict_types=1);

namespace App\Support\Org\Synthesis;

use App\Support\Org\SynthesisResult;

/**
 * Chair-decides synthesis strategy.
 *
 * The designated chair's decision is final. Other council member votes are
 * recorded for context and conflict reporting but do not affect the outcome.
 *
 * Deterministic behavior:
 * - Finds the chair member from the member list (is_chair = true)
 * - Chair's decision becomes the final decision
 * - If chair has no response, result is null decision
 * - Other votes are recorded in vote breakdown
 * - Generates conflict entries when non-chair members disagree with chair
 */
class ChairDecidesSynthesisStrategy implements SynthesisStrategyInterface
{
    public function synthesize(array $responses, string $decisionField, array $memberList = []): SynthesisResult
    {
        if (empty($responses)) {
            return SynthesisResult::empty('chair_decides');
        }

        // Find the chair from member list
        $chairAgentId = null;
        foreach ($memberList as $member) {
            if (! empty($member['is_chair'])) {
                $chairAgentId = $member['agent_id'] ?? null;
                break;
            }
        }

        // Build response lookup by agent ID
        $responsesByAgent = [];
        foreach ($responses as $response) {
            $agentId = $response['agent_id'] ?? null;
            if ($agentId) {
                $responsesByAgent[$agentId] = $response;
            }
        }

        // Collect all votes for breakdown
        $voteBreakdown = [];
        $voteCounts = [];

        foreach ($responses as $response) {
            if (! isset($response[$decisionField])) {
                continue;
            }

            $decision = $response[$decisionField];
            $agentId = $response['agent_id'] ?? 'unknown';
            $isChair = $agentId === $chairAgentId;

            $voteBreakdown[] = [
                'agent_id' => $agentId,
                'decision' => $decision,
                'perspective' => $response['perspective'] ?? null,
                'reasoning' => $response['reasoning'] ?? null,
                'is_chair' => $isChair,
            ];

            $voteCounts[$decision] = ($voteCounts[$decision] ?? 0) + 1;
        }

        $totalVotes = array_sum($voteCounts);

        // Find chair's response
        $chairResponse = $chairAgentId ? ($responsesByAgent[$chairAgentId] ?? null) : null;
        $chairDecision = $chairResponse[$decisionField] ?? null;

        if ($chairDecision === null) {
            // Chair didn't vote or wasn't found - no decision can be made
            return SynthesisResult::tied(
                votesFor: 0,
                votesAgainst: 0,
                conflicts: $this->generateConflicts($responses, $decisionField, $chairDecision),
                voteBreakdown: $voteBreakdown,
                synthesisMode: 'chair_decides',
            );
        }

        // Count votes for/against chair's decision
        $votesFor = $voteCounts[$chairDecision] ?? 0;
        $votesAgainst = $totalVotes - $votesFor;

        // Generate conflicts (members who disagree with chair)
        $conflicts = $this->generateConflicts($responses, $decisionField, $chairDecision);

        return SynthesisResult::decided(
            decision: $chairDecision,
            votesFor: $votesFor,
            votesAgainst: $votesAgainst,
            conflicts: $conflicts,
            voteBreakdown: $voteBreakdown,
            synthesisMode: 'chair_decides',
        );
    }

    public function getMode(): string
    {
        return 'chair_decides';
    }

    /**
     * Generate conflict entries for members who disagree with the chair's decision.
     */
    private function generateConflicts(array $responses, string $decisionField, ?string $chairDecision): array
    {
        if ($chairDecision === null) {
            // Can't determine conflicts without chair decision
            return [];
        }

        $conflicts = [];
        foreach ($responses as $response) {
            $decision = $response[$decisionField] ?? null;
            if ($decision === null || $decision === $chairDecision) {
                continue; // No conflict if agrees with chair or no decision
            }

            $conflicts[] = [
                'agent_id' => $response['agent_id'] ?? 'unknown',
                'perspective' => $response['perspective'] ?? null,
                'decision' => $decision,
                'reasoning' => $response['reasoning'] ?? null,
                'conflict_with' => 'chair',
            ];
        }

        return $conflicts;
    }
}
