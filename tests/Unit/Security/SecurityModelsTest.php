<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\ContentTrustLevel;
use App\Enums\Security\DetectionPatternType;
use App\Enums\Security\InjectionSeverity;
use App\Enums\Security\SecurityEventType;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Security\AccountSecurityOverride;
use App\Models\Security\SecurityDetectionRule;
use App\Models\Security\SecurityEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_detection_rule_create_with_enum_casts(): void
    {
        $rule = SecurityDetectionRule::create([
            'pattern' => 'ignore previous instructions',
            'pattern_type' => DetectionPatternType::Keyword,
            'severity' => InjectionSeverity::High,
            'weight' => 0.8,
            'enabled' => true,
        ]);

        $rule->refresh();

        $this->assertInstanceOf(DetectionPatternType::class, $rule->pattern_type);
        $this->assertSame(DetectionPatternType::Keyword, $rule->pattern_type);
        $this->assertInstanceOf(InjectionSeverity::class, $rule->severity);
        $this->assertSame(InjectionSeverity::High, $rule->severity);
        $this->assertIsFloat($rule->weight);
        $this->assertEqualsWithDelta(0.8, $rule->weight, 0.01);
        $this->assertTrue($rule->enabled);
    }

    public function test_security_event_create_with_casts(): void
    {
        $event = SecurityEvent::create([
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'high',
            'details_json' => ['pattern' => 'test', 'score' => 0.9],
            'created_at' => now(),
        ]);

        $event->refresh();

        $this->assertInstanceOf(SecurityEventType::class, $event->event_type);
        $this->assertSame(SecurityEventType::InjectionDetected, $event->event_type);
        $this->assertIsArray($event->details_json);
        $this->assertSame('test', $event->details_json['pattern']);
    }

    public function test_security_event_scope_for_session(): void
    {
        $user = \App\Models\User::factory()->withPersonalTeam()->create();

        $sessionId = Str::uuid()->toString();
        $otherSessionId = Str::uuid()->toString();

        \Illuminate\Support\Facades\DB::table('runtime_sessions')->insert([
            ['id' => $sessionId, 'user_id' => $user->id, 'status' => 'running', 'mode' => 'safe', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => $otherSessionId, 'user_id' => $user->id, 'status' => 'running', 'mode' => 'safe', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        SecurityEvent::create([
            'runtime_session_id' => $sessionId,
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'high',
            'details_json' => ['test' => true],
            'created_at' => now(),
        ]);

        SecurityEvent::create([
            'runtime_session_id' => $otherSessionId,
            'event_type' => SecurityEventType::ContentBlocked,
            'severity' => 'medium',
            'details_json' => ['test' => true],
            'created_at' => now(),
        ]);

        $results = SecurityEvent::forSession($sessionId)->get();

        $this->assertCount(1, $results);
        $this->assertSame($sessionId, $results->first()->runtime_session_id);
    }

    public function test_security_event_scope_of_type(): void
    {
        SecurityEvent::create([
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'high',
            'details_json' => ['test' => true],
            'created_at' => now(),
        ]);

        SecurityEvent::create([
            'event_type' => SecurityEventType::ExfiltrationAttempt,
            'severity' => 'critical',
            'details_json' => ['test' => true],
            'created_at' => now(),
        ]);

        $results = SecurityEvent::ofType(SecurityEventType::InjectionDetected)->get();

        $this->assertCount(1, $results);
        $this->assertSame(SecurityEventType::InjectionDetected, $results->first()->event_type);
    }

    public function test_account_security_override_unique_constraint(): void
    {
        $accountId = Str::uuid()->toString();

        AccountSecurityOverride::create([
            'account_id' => $accountId,
            'config_key' => 'injection_threshold_safe',
            'config_value' => '0.4',
        ]);

        $this->expectException(QueryException::class);

        AccountSecurityOverride::create([
            'account_id' => $accountId,
            'config_key' => 'injection_threshold_safe',
            'config_value' => '0.5',
        ]);
    }

    public function test_runtime_tool_call_content_trust_level_casts_to_enum(): void
    {
        // We need a runtime_turn to satisfy the FK, so use DB directly
        $turnId = $this->createRuntimeTurn();

        $toolCall = RuntimeToolCall::create([
            'runtime_turn_id' => $turnId,
            'tool_name' => 'web.fetch',
            'arguments_json' => ['url' => 'https://example.com'],
            'result_json' => ['data' => 'test'],
            'status' => 'completed',
            'duration_ms' => 100,
            'content_trust_level' => ContentTrustLevel::Untrusted,
            'injection_score' => 0.75,
            'content_sanitized' => true,
        ]);

        $toolCall->refresh();

        $this->assertInstanceOf(ContentTrustLevel::class, $toolCall->content_trust_level);
        $this->assertSame(ContentTrustLevel::Untrusted, $toolCall->content_trust_level);
        $this->assertIsFloat($toolCall->injection_score);
        $this->assertEqualsWithDelta(0.75, $toolCall->injection_score, 0.01);
        $this->assertTrue($toolCall->content_sanitized);
    }

    public function test_runtime_tool_call_security_columns_nullable(): void
    {
        $turnId = $this->createRuntimeTurn();

        $toolCall = RuntimeToolCall::create([
            'runtime_turn_id' => $turnId,
            'tool_name' => 'fs.read',
            'arguments_json' => ['path' => '/tmp/test'],
            'result_json' => ['data' => 'test'],
            'status' => 'completed',
            'duration_ms' => 50,
        ]);

        $toolCall->refresh();

        $this->assertNull($toolCall->content_trust_level);
        $this->assertNull($toolCall->injection_score);
        $this->assertNull($toolCall->injection_action);
        $this->assertFalse($toolCall->content_sanitized);
    }

    private function createRuntimeTurn(): string
    {
        $userId = \App\Models\User::factory()->withPersonalTeam()->create()->id;

        $sessionId = Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('runtime_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'status' => 'running',
            'mode' => 'safe',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $turnId = Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('runtime_turns')->insert([
            'id' => $turnId,
            'runtime_session_id' => $sessionId,
            'sequence' => 1,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $turnId;
    }
}
