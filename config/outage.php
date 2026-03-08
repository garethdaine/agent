<?php

declare(strict_types=1);

return [
    'auto_protect_enabled' => (bool) env('OUTAGE_AUTO_PROTECT_ENABLED', false),

    'message' => env('OUTAGE_AUTO_PROTECT_MESSAGE', 'System is in maintenance mode. Please try again later.'),
];
