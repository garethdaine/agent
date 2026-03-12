<?php

declare(strict_types=1);

namespace App\Support\Delegation\Verification\DTOs;

/**
 * Aggregate result of evaluating all acceptance criteria for a task.
 */
readonly class AcceptanceCriteriaResult
{
    /**
     * @param  bool  $allPassed  Whether every criterion passed
     * @param  array<CriterionResult>  $results  Individual criterion results
     */
    public function __construct(
        public bool $allPassed,
        public array $results,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'all_passed' => $this->allPassed,
            'results' => array_map(fn (CriterionResult $r) => $r->toArray(), $this->results),
        ];
    }
}
