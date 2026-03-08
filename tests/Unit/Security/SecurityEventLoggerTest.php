<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Security\SecurityEvent;
use App\Services\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityEventLoggerTest extends TestCase
{
    use RefreshDatabase;

    private SecurityEventLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new SecurityEventLogger;
    }

    public function test_log_injection_detected_creates_event(): void
    {
        $this->logger->logInjectionDetected(
            score: 0.85,
            source: 'web.fetch',
            content: 'suspicious content',
        );

        $this->assertDatabaseHas('security_events', [
            'event_type' => SecurityEventType::InjectionDetected->value,
        ]);

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertEquals('injection_detected', $event->event_type->value);
        $this->assertArrayHasKey('timestamp', $event->details_json);
        $this->assertEquals(0.85, $event->details_json['score']);
        $this->assertEquals('web.fetch', $event->details_json['source']);
    }

    public function test_log_content_blocked_creates_event(): void
    {
        $this->logger->logContentBlocked(
            reason: 'injection score exceeded threshold',
            source: 'mcp.call',
        );

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertEquals(SecurityEventType::ContentBlocked, $event->event_type);
        $this->assertArrayHasKey('timestamp', $event->details_json);
        $this->assertEquals('injection score exceeded threshold', $event->details_json['reason']);
    }

    public function test_log_content_stripped_creates_event(): void
    {
        $this->logger->logContentStripped(
            source: 'browser.dom',
            elementsStripped: ['script', 'style'],
        );

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertEquals(SecurityEventType::ContentStripped, $event->event_type);
        $this->assertEquals(['script', 'style'], $event->details_json['elements_stripped']);
    }

    public function test_log_exfiltration_attempt_creates_event(): void
    {
        $this->logger->logExfiltrationAttempt(
            pattern: 'credential_pattern',
            target: 'https://evil.com',
        );

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertEquals(SecurityEventType::ExfiltrationAttempt, $event->event_type);
        $this->assertEquals('credential_pattern', $event->details_json['pattern']);
        $this->assertEquals('https://evil.com', $event->details_json['target']);
    }

    public function test_log_escalation_triggered_creates_event(): void
    {
        $this->logger->logEscalationTriggered(
            reason: 'tainted turn mutation',
            toolName: 'fs.write',
        );

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertEquals(SecurityEventType::EscalationTriggered, $event->event_type);
        $this->assertEquals('tainted turn mutation', $event->details_json['reason']);
        $this->assertEquals('fs.write', $event->details_json['tool_name']);
    }

    public function test_events_include_timestamp_in_details(): void
    {
        $this->logger->logInjectionDetected(
            score: 0.5,
            source: 'test',
        );

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertArrayHasKey('timestamp', $event->details_json);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $event->details_json['timestamp']);
    }

    public function test_nullable_session_and_tool_call_ids(): void
    {
        $this->logger->logInjectionDetected(
            score: 0.9,
            source: 'test',
            sessionId: null,
            toolCallId: null,
        );

        $event = SecurityEvent::latest('created_at')->first();
        $this->assertNull($event->runtime_session_id);
        $this->assertNull($event->runtime_tool_call_id);
    }
}
