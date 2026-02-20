<?php

namespace App\Support\TaskProviders;

use App\Support\TaskProviders\Contracts\TaskManagementProviderDriver;
use App\Support\TaskProviders\Drivers\LinearTaskManagementProvider;
use InvalidArgumentException;

class TaskManagementProviderManager
{
    public function driver(string $driver): TaskManagementProviderDriver
    {
        return match (strtolower(trim($driver))) {
            'linear' => app(LinearTaskManagementProvider::class),
            default => throw new InvalidArgumentException(sprintf('Unsupported task provider driver [%s].', $driver)),
        };
    }

    /**
     * @return array<int, string>
     */
    public function supportedDrivers(): array
    {
        return ['linear'];
    }
}
