<?php

declare(strict_types=1);

return [
    'enabled' => env('BILLING_ENABLED', false),

    'plans' => [
        'pilot' => env('STRIPE_PRICE_PILOT', 'price_pilot'),
        'monthly' => env('STRIPE_PRICE_MONTHLY', 'price_monthly'),
    ],

    'meters' => [
        'runs' => env('STRIPE_METER_RUNS', 'agent_runs'),
        'tokens' => env('STRIPE_METER_TOKENS', 'agent_tokens'),
    ],

    'portal' => [
        'return_url' => env('CASHIER_BILLING_PORTAL_RETURN_URL', '/dashboard'),
    ],
];
