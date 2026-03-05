<?php

return [
    'templates' => [
        'accountancy_client_reporting' => [
            'name' => 'Accountancy client reporting',
            'description' => 'Scheduled runs that produce client-facing reports from your books.',
            'runner_type' => 'claude',
            'cron_expression' => '0 8 * * 1-5',
            'defaults' => [],
        ],
        'msp_health_checks' => [
            'name' => 'MSP health checks',
            'description' => 'Periodic health checks across monitored systems.',
            'runner_type' => 'claude',
            'cron_expression' => '0 */6 * * *',
            'defaults' => [],
        ],
    ],
];
