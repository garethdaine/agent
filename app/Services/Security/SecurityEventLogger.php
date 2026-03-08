<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Security\SecurityEvent;
use Illuminate\Support\Facades\Log;

class SecurityEventLogger
{
    public function logInjectionDetected(
        float $score,
        string $source,
        ?string $content = null,
        ?string $sessionId = null,
        ?string $toolCallId = null,
    ): void {
        $this->createEvent(
            type: SecurityEventType::InjectionDetected,
            severity: $score >= 0.7 ? 'high' : ($score >= 0.4 ? 'medium' : 'low'),
            details: array_filter([
                'score' => $score,
                'source' => $source,
                'content_preview' => $content ? mb_substr($content, 0, 200) : null,
            ], fn ($v) => $v !== null),
            sessionId: $sessionId,
            toolCallId: $toolCallId,
        );
    }

    public function logContentBlocked(
        string $reason,
        string $source,
        ?string $sessionId = null,
        ?string $toolCallId = null,
    ): void {
        $this->createEvent(
            type: SecurityEventType::ContentBlocked,
            severity: 'high',
            details: [
                'reason' => $reason,
                'source' => $source,
            ],
            sessionId: $sessionId,
            toolCallId: $toolCallId,
        );
    }

    public function logContentStripped(
        string $source,
        array $elementsStripped,
        ?string $sessionId = null,
        ?string $toolCallId = null,
    ): void {
        $this->createEvent(
            type: SecurityEventType::ContentStripped,
            severity: 'low',
            details: [
                'source' => $source,
                'elements_stripped' => $elementsStripped,
            ],
            sessionId: $sessionId,
            toolCallId: $toolCallId,
        );
    }

    public function logExfiltrationAttempt(
        string $pattern,
        string $target,
        ?string $sessionId = null,
        ?string $toolCallId = null,
    ): void {
        $this->createEvent(
            type: SecurityEventType::ExfiltrationAttempt,
            severity: 'critical',
            details: [
                'pattern' => $pattern,
                'target' => $target,
            ],
            sessionId: $sessionId,
            toolCallId: $toolCallId,
        );
    }

    public function logEscalationTriggered(
        string $reason,
        string $toolName,
        ?string $sessionId = null,
        ?string $toolCallId = null,
    ): void {
        $this->createEvent(
            type: SecurityEventType::EscalationTriggered,
            severity: 'medium',
            details: [
                'reason' => $reason,
                'tool_name' => $toolName,
            ],
            sessionId: $sessionId,
            toolCallId: $toolCallId,
        );
    }

    private function createEvent(
        SecurityEventType $type,
        string $severity,
        array $details,
        ?string $sessionId,
        ?string $toolCallId,
    ): void {
        $details['timestamp'] = now()->toIso8601String();

        try {
            SecurityEvent::create([
                'runtime_session_id' => $sessionId,
                'runtime_tool_call_id' => $toolCallId,
                'event_type' => $type,
                'severity' => $severity,
                'details_json' => $details,
                'created_at' => now(),
            ]);

            try {
                Log::channel('security')->info("Security event: {$type->value}", $details);
            } catch (\Throwable) {
                Log::info("Security event: {$type->value}", $details);
            }
        } catch (\Throwable $e) {
            try {
                Log::error("Failed to log security event: {$type->value}", [
                    'error' => $e->getMessage(),
                    'details' => $details,
                ]);
            } catch (\Throwable) {
                // Silently fail — fire-and-forget
            }
        }
    }
}
