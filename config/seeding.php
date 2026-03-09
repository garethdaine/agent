<?php

declare(strict_types=1);

return [
    'admin_password' => env('ADMIN_PASSWORD'),
    'e2e_seed_user' => env('E2E_SEED_USER', false),
    'test_user_email' => env('TEST_USER_EMAIL', 'test@example.com'),
    'test_user_password' => env('TEST_USER_PASSWORD', 'password'),
];
