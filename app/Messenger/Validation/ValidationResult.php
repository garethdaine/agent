<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

/**
 * Result of credential validation for messenger providers.
 *
 * Contains validation status, field-specific errors, and metadata
 * about the validated account (e.g., bot username, team info).
 */
readonly class ValidationResult
{
    /**
     * @param  bool  $valid  Whether validation passed
     * @param  array<string, string>  $errors  Field-specific error messages
     * @param  array<string, mixed>  $metadata  Additional info about the validated account
     */
    public function __construct(
        private bool $valid,
        private array $errors = [],
        private array $metadata = [],
    ) {}

    /**
     * Create a successful validation result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function success(array $metadata = []): self
    {
        return new self(true, [], $metadata);
    }

    /**
     * Create a failed validation result.
     *
     * @param  array<string, string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors, []);
    }

    /**
     * Create a failed validation result with a single field error.
     */
    public static function fieldError(string $field, string $message): self
    {
        return new self(false, [$field => $message], []);
    }

    /**
     * Check if validation passed.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Get validation errors keyed by field name.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get metadata about the validated account.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if a specific field has an error.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get error message for a specific field.
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Merge another validation result into this one.
     * Returns a new ValidationResult with combined errors.
     */
    public function merge(self $other): self
    {
        if ($this->valid && $other->valid) {
            return new self(true, [], array_merge($this->metadata, $other->metadata));
        }

        return new self(
            false,
            array_merge($this->errors, $other->errors),
            []
        );
    }
}
