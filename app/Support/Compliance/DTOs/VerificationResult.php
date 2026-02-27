<?php

namespace App\Support\Compliance\DTOs;

readonly class VerificationResult
{
    /**
     * @param  bool  $satisfied  Whether all required evidence is present
     * @param  array<int, string>  $missing  Array of missing evidence types
     * @param  array<int, string>  $present  Array of present evidence types
     */
    public function __construct(
        public bool $satisfied,
        public array $missing = [],
        public array $present = []
    ) {}
}
