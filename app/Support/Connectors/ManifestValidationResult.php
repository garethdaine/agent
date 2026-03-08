<?php

namespace App\Support\Connectors;

class ManifestValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public static function success(array $warnings = []): self
    {
        return new self(valid: true, warnings: $warnings);
    }

    public static function failure(array $errors, array $warnings = []): self
    {
        return new self(valid: false, errors: $errors, warnings: $warnings);
    }
}
