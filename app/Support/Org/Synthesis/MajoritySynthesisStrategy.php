<?php

namespace App\Support\Org\Synthesis;

use App\Support\Org\SynthesisResult;

/**
 * Majority vote synthesis strategy.
 *
 * Implements simple majority voting - the decision with more than 50% of
 * votes wins. If no decision has a clear majority (tie or three-way split),
 * the result is marked as a tie with no decision.
 *
 * Deterministic behavior:
 * - Counts votes for each unique decision value
 * - Decision with strict majority (> 50%) wins
 * - Ties or pluralities without majority result in null decision
 * - Generates conflict entries when responses disagree
 */
class MajoritySynthesisStrategy implements SynthesisStrategyInterface
{
    public function synthesize(array $responses, string $decisionField, array $memberList = []): SynthesisResult
    {
        if (empty($responses)) {
            return SynthesisResult::empty('majority');
        }

        // Count votes for each decision value
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

            $voteCounts[$decision] = ($voteCounts[$decision] ?? 0) + 1;
            $voteBreakdown[] = [
                'agent_id' => $agentId,
                'decision' => $decision,
                'perspective' => $perspective,
                'reasoning' => $reasoning,
            ];
            $validVoters[] = $response;
        }

        $totalVotes = array_sum($voteCounts);

        if ($totalVotes === 0) {
            return SynthesisResult::empty('majority');
        }

        // Find the decision with the most votes
        arsort($voteCounts);
        $topDecisions = array_keys($voteCounts);
        $topDecision = $topDecisions[0];
        $topCount = $voteCounts[$topDecision];

        // Check for strict majority (> 50%)
        $hasMajority = $topCount > ($totalVotes / 2);

        // Check for tie (multiple decisions with the same top count)
        $isTie = count(array_filter($voteCounts, fn ($count) => $count === $topCount)) > 1;

        // Generate conflict log when there are disagreements
        $conflicts = $this->generateConflicts($validVoters, $decisionField);

        if (! $hasMajority || $isTie) {
            return SynthesisResult::tied(
                votesFor: $topCount,
                votesAgainst: $totalVotes - $topCount,
                conflicts: $conflicts,
                voteBreakdown: $voteBreakdown,
                synthesisMode: 'majority',
            );
        }

        return SynthesisResult::decided(
            decision: $topDecision,
            votesFor: $topCount,
            votesAgainst: $totalVotes - $topCount,
            conflicts: $conflicts,
            voteBreakdown: $voteBreakdown,
            synthesisMode: 'majority',
        );
    }

    public function getMode(): string
    {
        return 'majority';
    }

    /**
     * Generate conflict entries when council members disagree.
     *
     * A conflict is generated for each unique decision position, capturing
     * the perspective and reasoning of the voters who took that position.
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
