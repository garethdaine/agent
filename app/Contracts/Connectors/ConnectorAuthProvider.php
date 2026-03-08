<?php

declare(strict_types=1);

namespace App\Contracts\Connectors;

interface ConnectorAuthProvider
{
    public function authenticate(array $config): array;

    public function refresh(array $credentials): array;

    public function revoke(array $credentials): void;

    public function isExpired(array $credentials): bool;
}
