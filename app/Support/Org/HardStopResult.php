<?php

namespace App\Support\Org;

/**
 * Data transfer object representing the result of a hard stop enforcement.
 */
class HardStopResult
{
    public const BEHAVIOR_BLOCK_UNDISPATCHED = 'block_undispatched';

    public const BEHAVIOR_TERMINATE_ALL = 'terminate_all';

    public const BEHAVIOR_NONE = 'none';

    public function __construct(
        public readonly string $behavior,
        public readonly bool $terminate_in_flight,
        public readonly bool $block_new_branches,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Create a result for block_undispatched behavior.
     * Running branches complete, new branches are blocked.
     */
    public static function blockUndispatched(?string $reason = null): self
    {
        return new self(
            behavior: self::BEHAVIOR_BLOCK_UNDISPATCHED,
            terminate_in_flight: false,
            block_new_branches: true,
            reason: $reason,
        );
    }

    /**
     * Create a result for terminate_all behavior.
     * All branches including running ones are terminated.
     */
    public static function terminateAll(?string $reason = null): self
    {
        return new self(
            behavior: self::BEHAVIOR_TERMINATE_ALL,
            terminate_in_flight: true,
            block_new_branches: true,
            reason: $reason,
        );
    }

    /**
     * Create a result for no action (thresholds not exceeded).
     */
    public static function none(): self
    {
        return new self(
            behavior: self::BEHAVIOR_NONE,
            terminate_in_flight: false,
            block_new_branches: false,
            reason: null,
        );
    }

    /**
     * Check if any enforcement action is being taken.
     */
    public function isEnforced(): bool
    {
        return $this->behavior !== self::BEHAVIOR_NONE;
    }
}
