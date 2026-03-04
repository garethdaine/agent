<?php

declare(strict_types=1);

namespace App\Enums;

enum GateTransitionSource: string
{
    case RELIABILITY_EVALUATION = 'reliability_evaluation';
    case MANUAL_OVERRIDE = 'manual_override';
    case BUDGET_ENFORCEMENT = 'budget_enforcement';
    case TELEMETRY_OUTAGE = 'telemetry_outage';
    case REPLAY_RECALCULATION = 'replay_recalculation';
}
