<?php

declare(strict_types=1);

namespace App\Contracts\Connectors;

interface ConnectorInterface
{
    public function getName(): string;

    public function getActions(): array;

    public function execute(string $action, array $parameters = []): mixed;

    public function healthCheck(): bool;
}
