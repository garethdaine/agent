<?php

declare(strict_types=1);

namespace App\Messenger\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Manages correlation IDs for tracking message lifecycles through the system.
 *
 * Correlation IDs are stored in Laravel's Context for thread-local storage,
 * ensuring they persist across method calls within the same request lifecycle
 * while being automatically isolated between concurrent requests.
 *
 * Scope: single message lifecycle (webhook receipt → parse → execute → response)
 * Resets for each new inbound message.
 */
class CorrelationContext
{
    private const KEY = 'messenger_correlation_id';

    /**
     * Generate a new correlation ID and store it in context.
     *
     * @return string The newly generated UUID
     */
    public function generate(): string
    {
        $id = Str::uuid()->toString();
        Context::add(self::KEY, $id);

        return $id;
    }

    /**
     * Get the current correlation ID from context.
     *
     * @return string|null The current correlation ID or null if not set
     */
    public function get(): ?string
    {
        return Context::get(self::KEY);
    }

    /**
     * Clear the current correlation ID and generate a new one.
     *
     * Call this at the start of each new inbound message to ensure
     * a fresh correlation ID for the new message lifecycle.
     *
     * @return string The newly generated UUID
     */
    public function reset(): string
    {
        Context::forget(self::KEY);

        return $this->generate();
    }

    /**
     * Get the current correlation ID, or generate a new one if not set.
     *
     * Useful for lazy initialization when you want to ensure a correlation
     * ID exists but don't want to override an existing one.
     *
     * @return string The current or newly generated UUID
     */
    public function getOrGenerate(): string
    {
        return $this->get() ?? $this->generate();
    }
}
