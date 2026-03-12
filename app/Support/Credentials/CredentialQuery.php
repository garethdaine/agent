<?php

declare(strict_types=1);

namespace App\Support\Credentials;

readonly class CredentialQuery
{
    public function __construct(
        public ?string $provider = null,
        public ?string $url = null,
        public ?string $type = null,
        public ?string $name = null,
    ) {}
}
