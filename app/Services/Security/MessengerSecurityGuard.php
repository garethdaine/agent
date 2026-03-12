<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\DTOs\Security\SanitizationResult;
use App\Enums\Messenger\ChatActionType;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Security\MessengerGroupPolicy;
use Illuminate\Support\Facades\Redis;

class MessengerSecurityGuard
{
    private const int RATE_WINDOW_SECONDS = 300;

    private const array HIGH_IMPACT_ACTIONS = [
        'jobs.create',
        'runs.run_now',
        'runs.stop',
        'general.task',
    ];

    public function __construct(
        private readonly InjectionDetectionEngine $injectionEngine,
        private readonly ContentSanitizer $contentSanitizer,
        private readonly SecurityConfigProvider $configProvider,
        private readonly SecurityEventLogger $eventLogger,
        private readonly CredentialRedactor $credentialRedactor = new CredentialRedactor,
    ) {}

    public function scanAttachment(string $content, RuntimeMode $mode, ?int $accountId = null): SanitizationResult
    {
        $detectionResult = $this->injectionEngine->scan($content, $mode, $accountId);
        $threshold = $this->configProvider->getThreshold($mode, $accountId);

        if ($detectionResult->score >= $threshold) {
            $this->eventLogger->logInjectionDetected(
                $detectionResult->score,
                'attachment.scan',
                $content,
            );

            return $this->contentSanitizer->sanitize($content, 'attachment.scan', $accountId);
        }

        // Check if content needs truncation even when clean
        $maxTokens = (int) $this->configProvider->get('tool_result_max_tokens', $accountId);
        $estimatedTokens = (int) ceil(mb_strlen($content) / 4);

        if ($estimatedTokens > $maxTokens) {
            return $this->contentSanitizer->sanitize($content, 'attachment.scan', $accountId);
        }

        return new SanitizationResult(
            content: $content,
            sanitized: false,
            truncated: false,
            originalTokenCount: $estimatedTokens,
        );
    }

    public function evaluateGroupMessage(string $connectorId, string $providerUserId, bool $isPaired, ?int $accountId = null): ?MessengerGroupPolicy
    {
        if ($isPaired) {
            return null;
        }

        $policyValue = (string) $this->configProvider->get('messenger_group_policy', $accountId);

        return MessengerGroupPolicy::tryFrom($policyValue);
    }

    /**
     * @return array{allowed: bool, remaining: int, message?: string}
     */
    public function checkRateLimit(string $connectorId, string $providerUserId, ?int $accountId = null): array
    {
        $key = "security:rate:{$connectorId}:{$providerUserId}";
        $limit = (int) $this->configProvider->get('messenger_rate_limit', $accountId);
        $now = (string) microtime(true);
        $windowStart = (string) (microtime(true) - self::RATE_WINDOW_SECONDS);

        $redis = Redis::connection('cache');

        // Remove entries older than the sliding window
        $redis->zremrangebyscore($key, '-inf', $windowStart);

        // Count current entries
        $count = (int) $redis->zcard($key);

        if ($count >= $limit) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'message' => "Rate limit exceeded: {$count}/{$limit} actions in the last 5 minutes. Please wait before trying again.",
            ];
        }

        // Add current action
        $redis->zadd($key, [$now => (float) $now]);
        $redis->expire($key, self::RATE_WINDOW_SECONDS);

        return [
            'allowed' => true,
            'remaining' => $limit - $count - 1,
        ];
    }

    public function sanitizeResponse(string $content): string
    {
        $result = $this->contentSanitizer->sanitize($content, 'messenger.response');

        $sanitized = $result->content;

        // Neutralize javascript: links in markdown
        $sanitized = str_replace('](javascript:', '](blocked:', $sanitized);

        // Redact credential values (passwords, API keys, tokens, etc.)
        // Defence-in-depth: even if the agent includes secrets in its response,
        // they must never reach the user via chat.
        $redactionResult = $this->credentialRedactor->redact($sanitized);
        $sanitized = $redactionResult['content'];

        return $sanitized;
    }

    public function requiresHighImpactConfirmation(ChatActionType $action, RuntimeMode $mode, ?int $accountId = null): bool
    {
        if (! $this->configProvider->get('messenger_high_impact_confirmation', $accountId)) {
            return false;
        }

        if ($mode === RuntimeMode::Safe) {
            return false;
        }

        return in_array($action->value, self::HIGH_IMPACT_ACTIONS, true);
    }
}
