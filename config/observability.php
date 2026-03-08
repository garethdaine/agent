<?php

declare(strict_types=1);

return [
    'ingest_lag_warning_seconds' => (int) env('OBSERVABILITY_INGEST_LAG_WARNING', 300),

    'projection_backlog_warning' => (int) env('OBSERVABILITY_PROJECTION_BACKLOG_WARNING', 1000),

    'sequence_violation_alert' => (bool) env('OBSERVABILITY_SEQUENCE_VIOLATION_ALERT', true),
];
