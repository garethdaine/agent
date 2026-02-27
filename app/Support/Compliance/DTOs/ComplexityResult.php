<?php

namespace App\Support\Compliance\DTOs;

readonly class ComplexityResult
{
    /**
     * @param  string  $classification  The complexity classification: 'simple' or 'non_trivial'
     * @param  array<int, string>  $evidence  Array of threshold violations or override indicators
     * @param  bool  $overrideApplied  Whether an explicit override was applied
     */
    public function __construct(
        public string $classification,
        public array $evidence = [],
        public bool $overrideApplied = false
    ) {}

    /**
     * Check if the classification is non_trivial.
     */
    public function isNonTrivial(): bool
    {
        return $this->classification === 'non_trivial';
    }
}
