<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\DTOs\Security\SecurityToolResult;
use App\Enums\Security\ContentTrustLevel;
use App\Enums\Security\InjectionAction;

class ContentSecurityMiddleware
{
    public function __construct(
        private readonly ContentTrustClassifier $classifier,
        private readonly InjectionDetectionEngine $detector,
        private readonly ContentSanitizer $sanitizer,
        private readonly InstructionFirewall $firewall,
        private readonly SecurityEventLogger $logger,
        private readonly SecurityConfigProvider $config,
    ) {}

    public function processResult(
        ToolResult $result,
        string $toolName,
        array $args,
        RuntimeContext $context,
        TurnSecurityContext $turnContext,
    ): SecurityToolResult {
        $sourceIdentifier = $this->resolveSourceIdentifier($toolName, $args);

        // 1. Classify
        $trustLevel = $this->classifier->classifyToolResult($toolName, $args);

        // 2. Create initial SecurityToolResult
        $securityResult = SecurityToolResult::fromToolResult($result, $trustLevel, $sourceIdentifier);

        // 3. Injection detection for UNTRUSTED or CONTEXTUAL content
        if ($trustLevel !== ContentTrustLevel::Trusted && $this->config->get('injection_detection_enabled')) {
            $content = $this->stringify($result->data);
            $detection = $this->detector->scan($content, $context->mode);

            $securityResult = $securityResult->withInjectionResult(
                $detection->score,
                $detection->recommendedAction,
            );

            // Act on detection result
            if ($detection->recommendedAction === InjectionAction::Block) {
                $this->logger->logContentBlocked(
                    reason: 'Injection detected with BLOCK action',
                    source: $sourceIdentifier,
                    sessionId: $context->session->id ?? null,
                );

                $blockedResult = ToolResult::failure(
                    'Content blocked: potential prompt injection detected in tool result from '.$sourceIdentifier,
                    $result->durationMs,
                );

                $securityResult = new SecurityToolResult(
                    original: $blockedResult,
                    contentTrustLevel: $trustLevel,
                    injectionScore: $detection->score,
                    injectionAction: InjectionAction::Block,
                    sanitized: false,
                    originalTokenCount: 0,
                    truncated: false,
                    sourceIdentifier: $sourceIdentifier,
                );

                $turnContext->incrementToolCall();

                if ($trustLevel === ContentTrustLevel::Untrusted) {
                    $turnContext->markTainted($sourceIdentifier);
                    $turnContext->incrementUntrustedResult();
                }

                return $securityResult;
            }

            if ($detection->recommendedAction === InjectionAction::Warn) {
                $this->logger->logInjectionDetected(
                    score: $detection->score,
                    source: $sourceIdentifier,
                    sessionId: $context->session->id ?? null,
                );
            }

            if ($detection->recommendedAction === InjectionAction::Strip) {
                $this->logger->logInjectionDetected(
                    score: $detection->score,
                    source: $sourceIdentifier,
                    sessionId: $context->session->id ?? null,
                );
            }
        }

        // 4. Sanitize (HTML/token)
        $content = $this->stringify($result->data);
        $sanitizationResult = $this->sanitizer->sanitize($content, $sourceIdentifier);

        $securityResult = $securityResult->withSanitization(
            $sanitizationResult->sanitized,
            $sanitizationResult->originalTokenCount,
            $sanitizationResult->truncated,
        );

        // If content was modified by sanitization, wrap in a new ToolResult
        if ($sanitizationResult->sanitized || $sanitizationResult->truncated) {
            $sanitizedToolResult = ToolResult::success(
                $sanitizationResult->content,
                $result->durationMs,
                $result->artifacts,
            );

            $securityResult = new SecurityToolResult(
                original: $sanitizedToolResult,
                contentTrustLevel: $securityResult->contentTrustLevel,
                injectionScore: $securityResult->injectionScore,
                injectionAction: $securityResult->injectionAction,
                sanitized: $securityResult->sanitized,
                originalTokenCount: $securityResult->originalTokenCount,
                truncated: $securityResult->truncated,
                sourceIdentifier: $securityResult->sourceIdentifier,
            );

            if ($sanitizationResult->strippedElements !== []) {
                $this->logger->logContentStripped(
                    source: $sourceIdentifier,
                    elementsStripped: $sanitizationResult->strippedElements,
                    sessionId: $context->session->id ?? null,
                );
            }
        }

        // 5. Taint propagation
        if ($trustLevel === ContentTrustLevel::Untrusted) {
            $turnContext->markTainted($sourceIdentifier);
            $turnContext->incrementUntrustedResult();
        }

        // 6. Increment tool call count
        $turnContext->incrementToolCall();

        return $securityResult;
    }

    public function checkPreExecution(
        string $qualifiedToolName,
        array $args,
        RuntimeContext $context,
        TurnSecurityContext $turnContext,
    ): ?ToolResult {
        $decision = $this->firewall->evaluate(
            $qualifiedToolName,
            $args,
            $turnContext,
            $context->mode,
        );

        if ($decision->requiresEscalation) {
            return ToolResult::success(
                data: [
                    'status' => 'pending_approval',
                    'reason' => $decision->reason,
                    'taint_source' => $decision->taintSource,
                ],
                durationMs: 0,
            );
        }

        if (! $decision->allowed) {
            return ToolResult::failure(
                error: $decision->reason ?? 'Blocked by security policy',
                durationMs: 0,
            );
        }

        return null;
    }

    private function stringify(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data) || is_object($data)) {
            return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $data;
    }

    private function resolveSourceIdentifier(string $toolName, array $args): string
    {
        if (isset($args['operation'])) {
            return $toolName.'.'.$args['operation'];
        }

        return $toolName;
    }
}
