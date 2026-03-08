<?php

declare(strict_types=1);

namespace App\Support\Delegation;

/**
 * Represents the result of validating a delegation contract.
 *
 * @see ContractValidator
 */
final readonly class ValidationResult
{
    /**
     * @param  bool  $valid  Whether the contract passes all validation rules
     * @param  array<int, string>  $errors  List of validation error messages
     * @param  array<int, string>  $warnings  List of non-fatal warnings
     */
    public function __construct(
        private bool $valid,
        private array $errors = [],
        private array $warnings = [],
    ) {}

    /**
     * Create a passing validation result.
     *
     * @param  array<int, string>  $warnings
     */
    public static function passed(array $warnings = []): self
    {
        return new self(valid: true, errors: [], warnings: $warnings);
    }

    /**
     * Create a failing validation result.
     *
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    public static function failed(array $errors, array $warnings = []): self
    {
        return new self(valid: false, errors: $errors, warnings: $warnings);
    }

    /**
     * Whether the contract passed validation.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Get all validation error messages.
     *
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warning messages.
     *
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
