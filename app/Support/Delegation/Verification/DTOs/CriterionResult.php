<?php

declare(strict_types=1);

namespace App\Support\Delegation\Verification\DTOs;

/**
 * Result of evaluating a single acceptance criterion.
 */
readonly class CriterionResult
{
    public function __construct(
        public bool $passed,
        public string $type,
        public string $description,
        public ?string $message = null,
    ) {}

    public static function passed(string $type, string $description): self
    {
        return new self(true, $type, $description);
    }

    public static function failed(string $message, string $type = '', string $description = ''): self
    {
        return new self(false, $type, $description, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'type' => $this->type,
            'description' => $this->description,
            'message' => $this->message,
        ];
    }
}
