<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\ContentTrustLevel;
use App\Enums\Security\DetectionPatternType;
use App\Enums\Security\ExfiltrationPattern;
use App\Enums\Security\InjectionAction;
use App\Enums\Security\InjectionSeverity;
use App\Enums\Security\MessengerGroupPolicy;
use App\Enums\Security\SecurityEventType;
use PHPUnit\Framework\TestCase;

class SecurityEnumsTest extends TestCase
{
    public function test_content_trust_level_has_three_cases(): void
    {
        $this->assertCount(3, ContentTrustLevel::cases());
        $this->assertNotNull(ContentTrustLevel::from('trusted'));
        $this->assertNotNull(ContentTrustLevel::from('contextual'));
        $this->assertNotNull(ContentTrustLevel::from('untrusted'));
    }

    public function test_injection_action_has_four_cases(): void
    {
        $this->assertCount(4, InjectionAction::cases());
        $this->assertNotNull(InjectionAction::from('strip'));
        $this->assertNotNull(InjectionAction::from('warn'));
        $this->assertNotNull(InjectionAction::from('block'));
        $this->assertNotNull(InjectionAction::from('log'));
    }

    public function test_injection_severity_has_four_cases(): void
    {
        $this->assertCount(4, InjectionSeverity::cases());
        $this->assertNotNull(InjectionSeverity::from('low'));
        $this->assertNotNull(InjectionSeverity::from('medium'));
        $this->assertNotNull(InjectionSeverity::from('high'));
        $this->assertNotNull(InjectionSeverity::from('critical'));
    }

    public function test_detection_pattern_type_has_three_cases(): void
    {
        $this->assertCount(3, DetectionPatternType::cases());
        $this->assertNotNull(DetectionPatternType::from('regex'));
        $this->assertNotNull(DetectionPatternType::from('keyword'));
        $this->assertNotNull(DetectionPatternType::from('heuristic'));
    }

    public function test_security_event_type_has_six_cases(): void
    {
        $this->assertCount(6, SecurityEventType::cases());
        $this->assertNotNull(SecurityEventType::from('injection_detected'));
        $this->assertNotNull(SecurityEventType::from('content_blocked'));
        $this->assertNotNull(SecurityEventType::from('content_stripped'));
        $this->assertNotNull(SecurityEventType::from('exfiltration_attempt'));
        $this->assertNotNull(SecurityEventType::from('escalation_triggered'));
        $this->assertNotNull(SecurityEventType::from('rule_purged'));
    }

    public function test_messenger_group_policy_has_two_cases(): void
    {
        $this->assertCount(2, MessengerGroupPolicy::cases());
        $this->assertNotNull(MessengerGroupPolicy::from('ignore'));
        $this->assertNotNull(MessengerGroupPolicy::from('low_trust'));
    }

    public function test_exfiltration_pattern_has_five_cases(): void
    {
        $this->assertCount(5, ExfiltrationPattern::cases());
        $this->assertNotNull(ExfiltrationPattern::from('session_token_echo'));
        $this->assertNotNull(ExfiltrationPattern::from('conversation_reflection'));
        $this->assertNotNull(ExfiltrationPattern::from('system_prompt_leak'));
        $this->assertNotNull(ExfiltrationPattern::from('credential_pattern'));
        $this->assertNotNull(ExfiltrationPattern::from('pii_echo'));
    }

    public function test_all_enums_are_string_backed(): void
    {
        foreach (ContentTrustLevel::cases() as $case) {
            $this->assertIsString($case->value);
        }
        foreach (InjectionAction::cases() as $case) {
            $this->assertIsString($case->value);
        }
        foreach (InjectionSeverity::cases() as $case) {
            $this->assertIsString($case->value);
        }
        foreach (DetectionPatternType::cases() as $case) {
            $this->assertIsString($case->value);
        }
        foreach (SecurityEventType::cases() as $case) {
            $this->assertIsString($case->value);
        }
        foreach (MessengerGroupPolicy::cases() as $case) {
            $this->assertIsString($case->value);
        }
        foreach (ExfiltrationPattern::cases() as $case) {
            $this->assertIsString($case->value);
        }
    }
}
