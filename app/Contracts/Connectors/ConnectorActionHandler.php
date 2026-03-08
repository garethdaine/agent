<?php

declare(strict_types=1);

namespace App\Contracts\Connectors;

interface ConnectorActionHandler
{
    public function handle(array $request): array;
}
