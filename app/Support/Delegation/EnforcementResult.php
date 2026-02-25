<?php

namespace App\Support\Delegation;

/**
 * Represents the result of enforcing policy constraints on a delegation contract.
 *
 * @see ContractEnforcer
 */
final readonly class EnforcementResult
{
    /**
     * @param  array<string, mixed>  $narrowedConfig  The contract config after policy enforcement
     * @param  array<int, string>  $warnings  Warnings about scope reductions
     * @param  bool  $wasNarrowed  Whether any scope was reduced during enforcement
     */
    public function __construct(
        private array $narrowedConfig,
        private array $warnings = [],
        private bool $wasNarrowed = false,
    ) {}

    /**
     * Create a result where no narrowing was needed.
     *
     * @param  array<string, mixed>  $config
     */
    public static function unchanged(array $config): self
    {
        return new self(narrowedConfig: $config, warnings: [], wasNarrowed: false);
    }

    /**
     * Create a result where scope was narrowed.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $warnings
     */
    public static function narrowed(array $config, array $warnings): self
    {
        return new self(narrowedConfig: $config, warnings: $warnings, wasNarrowed: true);
    }

    /**
     * Get the contract config after policy enforcement.
     *
     * @return array<string, mixed>
     */
    public function narrowedConfig(): array
    {
        return $this->narrowedConfig;
    }

    /**
     * Get warnings about any scope reductions that were applied.
     *
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Whether any scope was narrowed during enforcement.
     */
    public function wasNarrowed(): bool
    {
        return $this->wasNarrowed;
    }
}
