<?php

namespace App\Enums;

enum TelemetryFailureClass: string
{
    case HARD_FAIL = 'hard_fail';
    case SOFT_FAIL = 'soft_fail';
    case DEGRADED = 'degraded';
    case CONTROL_FLOW = 'control_flow';
}
