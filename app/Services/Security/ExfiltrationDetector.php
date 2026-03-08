<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\DTOs\Security\ExfiltrationInspectionResult;
use App\Enums\Security\ExfiltrationPattern;

class ExfiltrationDetector
{
    /** @var array<string> */
    private readonly array $allowedHosts;

    /**
     * @param  array<string>|null  $allowedHosts  Override for testing; defaults to config('runtime.web.allowed_hosts')
     */
    public function __construct(
        private readonly SecurityConfigProvider $config,
        private readonly SecurityEventLogger $logger,
        ?array $allowedHosts = null,
    ) {
        $this->allowedHosts = $allowedHosts ?? (function_exists('config') ? (array) config('runtime.web.allowed_hosts', []) : []);
    }

    public function inspect(
        string $method,
        string $url,
        ?string $body,
        ?string $sessionId = null,
    ): ExfiltrationInspectionResult {
        $method = strtoupper($method);

        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return new ExfiltrationInspectionResult(blocked: false, matchedPatterns: [], reason: null);
        }

        if ($this->isAllowlistedHost($url)) {
            return new ExfiltrationInspectionResult(blocked: false, matchedPatterns: [], reason: null);
        }

        if ($body === null || $body === '') {
            return new ExfiltrationInspectionResult(blocked: false, matchedPatterns: [], reason: null);
        }

        $matched = [];

        foreach (ExfiltrationPattern::cases() as $pattern) {
            if ($this->inspectPattern($pattern, $body)) {
                $matched[] = $pattern;
            }
        }

        if (empty($matched)) {
            return new ExfiltrationInspectionResult(blocked: false, matchedPatterns: [], reason: null);
        }

        $patternNames = array_map(fn (ExfiltrationPattern $p) => $p->value, $matched);
        $reason = 'Exfiltration patterns detected: ' . implode(', ', $patternNames);

        $this->logger->logExfiltrationAttempt(
            pattern: implode(', ', $patternNames),
            target: $url,
            sessionId: $sessionId,
        );

        return new ExfiltrationInspectionResult(
            blocked: true,
            matchedPatterns: $matched,
            reason: $reason,
        );
    }

    public function inspectPattern(ExfiltrationPattern $pattern, string $body): bool
    {
        return match ($pattern) {
            ExfiltrationPattern::SessionTokenEcho => $this->detectSessionTokenEcho($body),
            ExfiltrationPattern::ConversationReflection => $this->detectConversationReflection($body),
            ExfiltrationPattern::SystemPromptLeak => $this->detectSystemPromptLeak($body),
            ExfiltrationPattern::CredentialPattern => $this->detectCredentialPattern($body),
            ExfiltrationPattern::PiiEcho => $this->detectPiiEcho($body),
        };
    }

    public function isAllowlistedHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            return false;
        }

        return in_array($host, $this->allowedHosts, true);
    }

    private function detectSessionTokenEcho(string $body): bool
    {
        $patterns = [
            '/session[_\-]?(?:id|token)\s*[=:]\s*[a-zA-Z0-9._\-]{16,}/i',
            '/bearer\s+[a-zA-Z0-9._\-]{20,}/i',
            '/api[_\-]?key\s*[=:]\s*[a-zA-Z0-9._\-]{20,}/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        return false;
    }

    private function detectConversationReflection(string $body): bool
    {
        $jsonPattern = preg_match_all('/"role"\s*:\s*"(?:user|assistant)"/i', $body);
        if ($jsonPattern >= 3) {
            return true;
        }

        $textPattern = preg_match_all('/(?:^|\n)\s*(?:User|Assistant)\s*:/m', $body);
        if ($textPattern >= 3) {
            return true;
        }

        return false;
    }

    private function detectSystemPromptLeak(string $body): bool
    {
        $patterns = [
            '/system[_\-]?prompt\s*[:=]/i',
            '/\bsystem_prompt\b/i',
            '/\bsystem prompt\b/i',
            '/\byou are a helpful assistant\b/i',
            '/\bignore previous instructions\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        return false;
    }

    private function detectCredentialPattern(string $body): bool
    {
        $patterns = [
            '/sk-[a-zA-Z0-9]{20,}/',
            '/-----BEGIN\s+(?:RSA\s+)?PRIVATE\s+KEY-----/',
            '/ghp_[a-zA-Z0-9]{36}/',
            '/gho_[a-zA-Z0-9]{36}/',
            '/github_pat_[a-zA-Z0-9_]{22,}/',
            '/xoxb-[a-zA-Z0-9\-]{20,}/',
            '/xoxp-[a-zA-Z0-9\-]{20,}/',
            '/password\s*[=:]\s*\S{8,}/i',
            '/AKIA[0-9A-Z]{16}/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        return false;
    }

    private function detectPiiEcho(string $body): bool
    {
        $emailCount = preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $body);

        return $emailCount >= 1;
    }
}
