<?php

declare(strict_types=1);

namespace App\Support\Connectors;

class RegistrySyncResult
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
        public readonly int $deprecated = 0,
        public readonly int $errors = 0,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->deprecated + $this->errors;
    }
}
