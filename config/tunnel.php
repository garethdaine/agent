<?php

declare(strict_types=1);

return [
    'enabled' => env('TUNNEL_ENABLED', false),
    'tunnel_name' => env('TUNNEL_NAME', ''),
    'tunnel_uuid' => env('TUNNEL_UUID', ''),
    'hostname' => env('TUNNEL_HOSTNAME', ''),
    'origin_url' => env('TUNNEL_ORIGIN_URL', 'http://localhost:8000'),
    'protocol' => env('TUNNEL_PROTOCOL', 'http2'),
    'cloudflared_binary' => env('CLOUDFLARED_BINARY', 'cloudflared'),
    'cloudflared_fallback_paths' => [
        '/usr/local/bin/cloudflared',
        '/usr/bin/cloudflared',
        '/opt/homebrew/bin/cloudflared',
    ],
    'cert_path' => env('TUNNEL_CERT_PATH'),
    'allow_install' => env('TUNNEL_ALLOW_INSTALL', false),
    'metrics_port' => env('TUNNEL_METRICS_PORT', 20241),
    'metrics_port_range_end' => 20249,
    'use_sync_start' => env('TUNNEL_USE_SYNC_START', false),
    'ip_allowlist' => [],
    'cloudflare_access_enabled' => false,
    'status_poll_interval' => 5,
    'min_version' => '2024.1.0',
];
