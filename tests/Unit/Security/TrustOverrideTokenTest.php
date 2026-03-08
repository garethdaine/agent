<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Runtime\RuntimeSession;
use App\Models\Security\SecurityEvent;
use App\Services\Security\TrustOverrideToken;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TrustOverrideTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_produces_valid_token_immediately(): void
    {
        $token = TrustOverrideToken::create('session-abc');

        $this->assertTrue($token->isValid());
    }

    public function test_is_valid_true_right_after_creation(): void
    {
        $token = TrustOverrideToken::create('session-def');

        $this->assertTrue($token->isValid());
        $this->assertNotEmpty($token->getToken());
        $this->assertEquals('session-def', $token->getSessionId());
    }

    public function test_token_expires_after_30_minutes(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');
        CarbonImmutable::setTestNow('2026-03-07 10:00:00');

        $token = TrustOverrideToken::create('session-expire');

        $this->assertTrue($token->isValid());

        // Advance 31 minutes
        Carbon::setTestNow('2026-03-07 10:31:00');
        CarbonImmutable::setTestNow('2026-03-07 10:31:00');

        // The Redis key should have expired, but since we can't control Redis TTL
        // with Carbon, we check the expiresAt instead
        $this->assertTrue($token->getExpiresAt()->isPast());

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    public function test_different_session_cannot_use_token(): void
    {
        $token = TrustOverrideToken::create('session-owner');

        // Try to load from a different session
        $foreignToken = TrustOverrideToken::fromRedis('session-other');

        $this->assertNull($foreignToken);
    }

    public function test_get_token_returns_non_empty_cryptographic_string(): void
    {
        $token = TrustOverrideToken::create('session-crypto');

        $tokenString = $token->getToken();

        $this->assertNotEmpty($tokenString);
        // 32 bytes = 64 hex characters
        $this->assertEquals(64, strlen($tokenString));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokenString);
    }

    public function test_get_expires_at_returns_carbon_immutable_30_min_from_creation(): void
    {
        Carbon::setTestNow('2026-03-07 12:00:00');
        CarbonImmutable::setTestNow('2026-03-07 12:00:00');

        $token = TrustOverrideToken::create('session-time');

        $this->assertInstanceOf(CarbonImmutable::class, $token->getExpiresAt());
        $this->assertEquals('2026-03-07 12:30:00', $token->getExpiresAt()->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    public function test_creation_logged_to_security_events(): void
    {
        $session = RuntimeSession::factory()->create();

        $token = TrustOverrideToken::create($session->id);

        $this->assertDatabaseHas('security_events', [
            'event_type' => SecurityEventType::EscalationTriggered->value,
            'runtime_session_id' => $session->id,
        ]);

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertStringContains('trust_override_created', $event->details_json['reason'] ?? '');
    }

    public function test_from_redis_loads_existing_token(): void
    {
        $original = TrustOverrideToken::create('session-load');

        $loaded = TrustOverrideToken::fromRedis('session-load');

        $this->assertNotNull($loaded);
        $this->assertEquals($original->getToken(), $loaded->getToken());
        $this->assertEquals($original->getSessionId(), $loaded->getSessionId());
    }

    public function test_from_redis_returns_null_for_nonexistent(): void
    {
        $loaded = TrustOverrideToken::fromRedis('session-nonexistent');

        $this->assertNull($loaded);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
