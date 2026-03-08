<?php

declare(strict_types=1);

namespace App\Support\Messenger;

use Illuminate\Support\Facades\Log;

class MessengerLogger
{
    private ?string $correlationId = null;

    private ?string $provider = null;

    private ?string $sessionId = null;

    private ?string $actionType = null;

    public function withCorrelation(string $correlationId): self
    {
        $clone = clone $this;
        $clone->correlationId = $correlationId;

        return $clone;
    }

    public function withProvider(string $provider): self
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    public function withSession(string $sessionId): self
    {
        $clone = clone $this;
        $clone->sessionId = $sessionId;

        return $clone;
    }

    public function withActionType(string $actionType): self
    {
        $clone = clone $this;
        $clone->actionType = $actionType;

        return $clone;
    }

    /**
     * Log an inbound message event.
     *
     * @param  array<string, mixed>  $context
     */
    public function inbound(string $message, array $context = []): void
    {
        Log::info(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    /**
     * Log an outbound message event.
     *
     * @param  array<string, mixed>  $context
     */
    public function outbound(string $message, array $context = []): void
    {
        Log::info(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    /**
     * Log an action execution event.
     *
     * @param  array<string, mixed>  $context
     */
    public function actionExecuted(string $message, array $context = []): void
    {
        Log::info(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    /**
     * Log an info-level event.
     *
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        Log::info(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    /**
     * Log a warning-level event.
     *
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        Log::warning(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    /**
     * Log an error-level event.
     *
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        Log::error(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    /**
     * Log a debug-level event.
     *
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): void
    {
        Log::debug(
            $this->formatMessage($message),
            $this->buildContext($context)
        );
    }

    private function formatMessage(string $message): string
    {
        return "[Messenger] {$message}";
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildContext(array $context): array
    {
        $baseContext = [];

        if ($this->correlationId !== null) {
            $baseContext['correlation_id'] = $this->correlationId;
        }

        if ($this->provider !== null) {
            $baseContext['provider'] = $this->provider;
        }

        if ($this->sessionId !== null) {
            $baseContext['session_id'] = $this->sessionId;
        }

        if ($this->actionType !== null) {
            $baseContext['action_type'] = $this->actionType;
        }

        return array_merge($baseContext, $context);
    }
}
