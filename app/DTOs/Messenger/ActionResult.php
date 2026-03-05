<?php

namespace App\DTOs\Messenger;

final readonly class ActionResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string>  $warnings
     * @param  array{input_tokens?: int, output_tokens?: int, cost_usd?: float}|null  $usage
     */
    public function __construct(
        public bool $success,
        public array $data = [],
        public ?string $message = null,
        public ?string $error = null,
        public array $warnings = [],
        public ?array $usage = null,
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $warnings
     */
    public static function success(
        array $data = [],
        ?string $message = null,
        array $warnings = []
    ): self {
        return new self(
            success: true,
            data: $data,
            message: $message,
            warnings: $warnings,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function failure(string $error, array $data = []): self
    {
        return new self(
            success: false,
            data: $data,
            error: $error,
        );
    }

    /**
     * Check if there are warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Convert to array for storage/serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'message' => $this->message,
            'error' => $this->error,
            'warnings' => $this->warnings,
            'usage' => $this->usage,
        ];
    }

    /**
     * Create from stored array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool) ($data['success'] ?? false),
            data: $data['data'] ?? [],
            message: $data['message'] ?? null,
            error: $data['error'] ?? null,
            warnings: $data['warnings'] ?? [],
            usage: is_array($data['usage'] ?? null) ? $data['usage'] : null,
        );
    }
}
