<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum SecurityEventType: string
{
    case InjectionDetected = 'injection_detected';
    case ContentBlocked = 'content_blocked';
    case ContentStripped = 'content_stripped';
    case ExfiltrationAttempt = 'exfiltration_attempt';
    case EscalationTriggered = 'escalation_triggered';
    case RulePurged = 'rule_purged';
}
