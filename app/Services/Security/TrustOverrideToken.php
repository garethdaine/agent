<?php

declare(strict_types=1);

namespace App\Services\Security;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;

class TrustOverrideToken
{
    private const int TTL_SECONDS = 1800; // 30 minutes

    private function __construct(
        private readonly string $sessionId,
        private readonly string $token,
        private readonly CarbonImmutable $expiresAt,
    ) {}

    public static function create(string $sessionId): self
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addSeconds(self::TTL_SECONDS);

        $data = json_encode([
            'token' => $token,
            'sessionId' => $sessionId,
            'createdAt' => CarbonImmutable::now()->toIso8601String(),
            'expiresAt' => $expiresAt->toIso8601String(),
        ]);

        Redis::connection('cache')->setex(
            self::redisKey($sessionId),
            self::TTL_SECONDS,
            $data,
        );

        // Log creation to security events
        $logger = app(SecurityEventLogger::class);
        $logger->logEscalationTriggered(
            reason: 'trust_override_created',
            toolName: 'trust_override',
            sessionId: $sessionId,
        );

        return new self($sessionId, $token, $expiresAt);
    }

    public static function fromRedis(string $sessionId): ?self
    {
        $data = Redis::connection('cache')->get(self::redisKey($sessionId));

        if ($data === null) {
            return null;
        }

        $decoded = json_decode($data, true);

        return new self(
            sessionId: $decoded['sessionId'],
            token: $decoded['token'],
            expiresAt: CarbonImmutable::parse($decoded['expiresAt']),
        );
    }

    public function isValid(): bool
    {
        return Redis::connection('cache')->exists(self::redisKey($this->sessionId)) > 0
            && ! $this->expiresAt->isPast();
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return $this->expiresAt;
    }

    private static function redisKey(string $sessionId): string
    {
        return "security:trust_override:{$sessionId}";
    }
}
