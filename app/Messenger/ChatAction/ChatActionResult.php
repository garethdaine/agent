<?php

namespace App\Messenger\ChatAction;

final readonly class ChatActionResult
{
    private function __construct(
        private bool $success,
        private string $message,
        private array $data = [],
        private bool $needsConfirmation = false,
    ) {}

    public static function success(string $message, array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
        );
    }

    public static function failure(string $message, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
        );
    }

    public static function pendingConfirmation(string $message, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
            needsConfirmation: true,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function requiresConfirmation(): bool
    {
        return $this->needsConfirmation;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'requires_confirmation' => $this->needsConfirmation,
        ];
    }
}
