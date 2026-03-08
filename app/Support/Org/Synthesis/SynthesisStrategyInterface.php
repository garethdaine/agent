<?php

declare(strict_types=1);

namespace App\Support\Org\Synthesis;

use App\Support\Org\SynthesisResult;

/**
 * Interface for deterministic council synthesis strategies.
 *
 * All synthesis strategies are deterministic - they apply rules-based logic
 * to aggregate council member responses into a single decision. Model-mediated
 * synthesis is opt-in and handled separately.
 */
interface SynthesisStrategyInterface
{
    /**
     * Synthesize responses from council members into a single result.
     *
     * @param  array  $responses  Array of council member responses, each containing at least the decision field
     * @param  string  $decisionField  The key in each response that contains the decision value
     * @param  array  $memberList  Optional member list from the council template (for weights, chair, etc.)
     * @return SynthesisResult The synthesized result including decision, vote counts, and conflicts
     */
    public function synthesize(array $responses, string $decisionField, array $memberList = []): SynthesisResult;

    /**
     * Get the synthesis mode identifier.
     */
    public function getMode(): string;
}
