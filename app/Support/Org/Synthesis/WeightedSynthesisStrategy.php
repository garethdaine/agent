<?php

declare(strict_types=1);

namespace App\Support\Org\Synthesis;

use App\Support\Org\SynthesisResult;

/**
 * Weighted vote synthesis strategy.
 *
 * Implements weighted voting - each council member's vote is multiplied by
 * their assigned weight. The decision with the highest total weight wins.
 *
 * Deterministic behavior:
 * - Sums weighted votes for each unique decision value
 * - Decision with highest total weighted score wins
 * - Ties (equal weighted scores) result in null decision
 * - Default weight is 1 if not specified in member list
 * - Generates conflict entries when responses disagree
 */
class WeightedSynthesisStrategy implements SynthesisStrategyInterface
{
    public function synthesize(array $responses, string $decisionField, array $memberList = []): SynthesisResult
    {
        if (empty($responses)) {
            return SynthesisResult::empty('weighted');
        }

        // Build weight lookup from member list
        $weights = [];
        foreach ($memberList as $member) {
            $agentId = $member['agent_id'] ?? null;
            if ($agentId) {
                $weights[$agentId] = (float) ($member['weight'] ?? 1.0);
            }
        }

        // Count weighted votes for each decision value
        $weightedCounts = [];
        $voteCounts = [];
        $voteBreakdown = [];
        $validVoters = [];

        foreach ($responses as $response) {
            if (! isset($response[$decisionField])) {
                continue; // Skip responses without a decision
            }

            $decision = $response[$decisionField];
            $agentId = $response['agent_id'] ?? 'unknown';
            $perspective = $response['perspective'] ?? null;
            $reasoning = $response['reasoning'] ?? null;

            // Get weight for this agent (default 1.0)
            $weight = $weights[$agentId] ?? 1.0;

            $weightedCounts[$decision] = ($weightedCounts[$decision] ?? 0.0) + $weight;
            $voteCounts[$decision] = ($voteCounts[$decision] ?? 0) + 1;

            $voteBreakdown[] = [
                'agent_id' => $agentId,
                'decision' => $decision,
                'perspective' => $perspective,
                'reasoning' => $reasoning,
                'weight' => $weight,
            ];
            $validVoters[] = $response;
        }

        $totalVotes = array_sum($voteCounts);
        $totalWeight = array_sum($weightedCounts);

        if ($totalVotes === 0) {
            return SynthesisResult::empty('weighted');
        }

        // Find the decision with the highest weighted score
        arsort($weightedCounts);
        $topDecisions = array_keys($weightedCounts);
        $topDecision = $topDecisions[0];
        $topWeightedScore = $weightedCounts[$topDecision];
        $topCount = $voteCounts[$topDecision];

        // Check for tie (multiple decisions with the same weighted score)
        $tolerance = 0.0001; // Float comparison tolerance
        $isTie = count(array_filter(
            $weightedCounts,
            fn ($score) => abs($score - $topWeightedScore) < $tolerance
        )) > 1;

        // Generate conflict log when there are disagreements
        $conflicts = $this->generateConflicts($validVoters, $decisionField);

        if ($isTie) {
            return SynthesisResult::weighted(
                decision: null,
                weightedFor: $topWeightedScore,
                weightedAgainst: $totalWeight - $topWeightedScore,
                votesFor: $topCount,
                votesAgainst: $totalVotes - $topCount,
                isTie: true,
                conflicts: $conflicts,
                voteBreakdown: $voteBreakdown,
            );
        }

        return SynthesisResult::weighted(
            decision: $topDecision,
            weightedFor: $topWeightedScore,
            weightedAgainst: $totalWeight - $topWeightedScore,
            votesFor: $topCount,
            votesAgainst: $totalVotes - $topCount,
            isTie: false,
            conflicts: $conflicts,
            voteBreakdown: $voteBreakdown,
        );
    }

    public function getMode(): string
    {
        return 'weighted';
    }

    /**
     * Generate conflict entries when council members disagree.
     */
    private function generateConflicts(array $responses, string $decisionField): array
    {
        // Group responses by decision
        $byDecision = [];
        foreach ($responses as $response) {
            $decision = $response[$decisionField] ?? null;
            if ($decision === null) {
                continue;
            }
            $byDecision[$decision][] = $response;
        }

        // If all agree, no conflicts
        if (count($byDecision) <= 1) {
            return [];
        }

        // Generate conflict entries for each position
        $conflicts = [];
        foreach ($byDecision as $decision => $voters) {
            foreach ($voters as $voter) {
                $conflicts[] = [
                    'agent_id' => $voter['agent_id'] ?? 'unknown',
                    'perspective' => $voter['perspective'] ?? null,
                    'decision' => $decision,
                    'reasoning' => $voter['reasoning'] ?? null,
                ];
            }
        }

        return $conflicts;
    }
}
