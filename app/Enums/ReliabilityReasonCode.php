<?php

declare(strict_types=1);

namespace App\Enums;

enum ReliabilityReasonCode: string
{
    case POLICY_BLOCKED = 'policy_blocked';
    case GUARDRAIL_BLOCKED = 'guardrail_blocked';
    case HARD_FAIL = 'hard_fail';
    case ASSISTED_SLA_EXPIRED = 'assisted_sla_expired';
    case VERIFICATION_FAILED = 'verification_failed';
    case HARD_FAIL_BURST = 'hard_fail_burst';
    case RELIABILITY_BELOW_THRESHOLD = 'reliability_below_threshold';
    case DEGRADED_RATE_EXCEEDED = 'degraded_rate_exceeded';
    case INSUFFICIENT_DATA = 'insufficient_data';
}
