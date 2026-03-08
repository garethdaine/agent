<?php

declare(strict_types=1);

namespace App\Support\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use InvalidArgumentException;

class ConnectorManager
{
    /**
     * @var array<string, class-string<ConnectorAdapterInterface>>
     */
    private array $adapters = [];

    /**
     * Register an adapter class for a provider.
     *
     * @param  class-string<ConnectorAdapterInterface>  $adapterClass
     */
    public function register(string $provider, string $adapterClass): void
    {
        $this->adapters[$provider] = $adapterClass;
    }

    /**
     * Resolve an adapter instance for the given provider.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $provider): ConnectorAdapterInterface
    {
        $provider = strtolower(trim($provider));

        if (! isset($this->adapters[$provider])) {
            throw new InvalidArgumentException(
                sprintf('Unsupported messenger provider [%s].', $provider)
            );
        }

        return app($this->adapters[$provider]);
    }

    /**
     * Get the list of supported providers.
     *
     * @return array<int, string>
     */
    public function getSupportedProviders(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * Check if a provider is supported.
     */
    public function isSupported(string $provider): bool
    {
        return isset($this->adapters[strtolower(trim($provider))]);
    }
}
