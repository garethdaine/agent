<?php

namespace App\Enums;

enum TelemetryFailureReasonCode: string
{
    case TIMEOUT = 'timeout';
    case RATE_LIMITED = 'rate_limited';
    case GUARDRAIL_BLOCKED = 'guardrail_blocked';
    case APPROVAL_REQUIRED = 'approval_required';
    case POLICY_BLOCKED = 'policy_blocked';
    case DEPENDENCY_ERROR = 'dependency_error';
    case INFRA_ERROR = 'infra_error';
    case PROVIDER_ERROR = 'provider_error';
    case VALIDATION_ERROR = 'validation_error';
    case OUTPUT_QUALITY_FAIL = 'output_quality_fail';
    case BUDGET_BREACH = 'budget_breach';
    case TELEMETRY_UNOBSERVABLE = 'telemetry_unobservable';
    case SKIPPED = 'skipped';
    case CANCELLED = 'cancelled';
}
