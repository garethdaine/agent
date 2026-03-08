<?php

declare(strict_types=1);

namespace App\Support\Org;

/**
 * Data transfer object representing the result of a council synthesis.
 *
 * This DTO captures the outcome of deterministic synthesis (majority, weighted, or chair_decides)
 * including vote tallies, conflicts between council members, and the final decision.
 */
readonly class SynthesisResult
{
    /**
     * @param  string|null  $decision  The synthesized decision (e.g., 'approve', 'reject'), or null if no consensus
     * @param  int  $votes_for  Number of votes for the winning decision
     * @param  int  $votes_against  Number of votes against the winning decision
     * @param  bool  $is_tie  Whether the vote resulted in a tie with no clear winner
     * @param  array  $conflicts  Array of conflict entries when members disagree
     * @param  array  $vote_breakdown  Detailed breakdown of how each agent voted
     * @param  string|null  $synthesis_mode  The synthesis mode used (majority, weighted, chair_decides)
     * @param  float  $weighted_score_for  For weighted synthesis, the total weighted score for the decision
     * @param  float  $weighted_score_against  For weighted synthesis, the total weighted score against the decision
     */
    public function __construct(
        public ?string $decision,
        public int $votes_for = 0,
        public int $votes_against = 0,
        public bool $is_tie = false,
        public array $conflicts = [],
        public array $vote_breakdown = [],
        public ?string $synthesis_mode = null,
        public float $weighted_score_for = 0.0,
        public float $weighted_score_against = 0.0,
    ) {}

    /**
     * Create a result for a clear majority/weighted decision.
     */
    public static function decided(
        string $decision,
        int $votesFor,
        int $votesAgainst,
        array $conflicts = [],
        array $voteBreakdown = [],
        ?string $synthesisMode = null,
    ): self {
        return new self(
            decision: $decision,
            votes_for: $votesFor,
            votes_against: $votesAgainst,
            is_tie: false,
            conflicts: $conflicts,
            vote_breakdown: $voteBreakdown,
            synthesis_mode: $synthesisMode,
        );
    }

    /**
     * Create a result for a tied vote with no clear winner.
     */
    public static function tied(
        int $votesFor,
        int $votesAgainst,
        array $conflicts = [],
        array $voteBreakdown = [],
        ?string $synthesisMode = null,
    ): self {
        return new self(
            decision: null,
            votes_for: $votesFor,
            votes_against: $votesAgainst,
            is_tie: true,
            conflicts: $conflicts,
            vote_breakdown: $voteBreakdown,
            synthesis_mode: $synthesisMode,
        );
    }

    /**
     * Create a result for an empty or invalid vote set.
     */
    public static function empty(?string $synthesisMode = null): self
    {
        return new self(
            decision: null,
            votes_for: 0,
            votes_against: 0,
            is_tie: false,
            conflicts: [],
            vote_breakdown: [],
            synthesis_mode: $synthesisMode,
        );
    }

    /**
     * Create a weighted result.
     */
    public static function weighted(
        ?string $decision,
        float $weightedFor,
        float $weightedAgainst,
        int $votesFor,
        int $votesAgainst,
        bool $isTie,
        array $conflicts = [],
        array $voteBreakdown = [],
    ): self {
        return new self(
            decision: $decision,
            votes_for: $votesFor,
            votes_against: $votesAgainst,
            is_tie: $isTie,
            conflicts: $conflicts,
            vote_breakdown: $voteBreakdown,
            synthesis_mode: 'weighted',
            weighted_score_for: $weightedFor,
            weighted_score_against: $weightedAgainst,
        );
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision,
            'votes_for' => $this->votes_for,
            'votes_against' => $this->votes_against,
            'is_tie' => $this->is_tie,
            'conflicts' => $this->conflicts,
            'vote_breakdown' => $this->vote_breakdown,
            'synthesis_mode' => $this->synthesis_mode,
            'weighted_score_for' => $this->weighted_score_for,
            'weighted_score_against' => $this->weighted_score_against,
        ];
    }
}
