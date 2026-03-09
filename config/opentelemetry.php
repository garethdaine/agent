<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Service Name
    |--------------------------------------------------------------------------
    |
    | The service name used to identify this application in traces. This value
    | is sent with every span and used by collectors to group telemetry data.
    |
    */

    'service_name' => env('OTEL_SERVICE_NAME', 'agentops'),

    /*
    |--------------------------------------------------------------------------
    | Traces Exporter
    |--------------------------------------------------------------------------
    |
    | The exporter used to emit trace data. Supported values: "console" (logs
    | to Laravel log channel), "otlp" (sends to an OTLP collector endpoint).
    | Default is "console" for local development.
    |
    */

    'traces_exporter' => env('OTEL_TRACES_EXPORTER', 'console'),

    /*
    |--------------------------------------------------------------------------
    | OTLP Endpoint
    |--------------------------------------------------------------------------
    |
    | The endpoint URL for the OTLP collector when using the "otlp" exporter.
    | Leave empty when using the console exporter.
    |
    */

    'otlp_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', ''),

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for OpenTelemetry tracing. When disabled, no spans are
    | created and no telemetry data is exported.
    |
    */

    'enabled' => env('OTEL_ENABLED', true),

];
