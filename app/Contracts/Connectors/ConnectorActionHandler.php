<?php

namespace App\Contracts\Connectors;

interface ConnectorActionHandler
{
    public function handle(array $request): array;
}
