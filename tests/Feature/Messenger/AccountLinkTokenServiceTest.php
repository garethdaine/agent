<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Services\Messenger\AccountLinkTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLinkTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(AccountLinkTokenService::class);
        $this->assertInstanceOf(AccountLinkTokenService::class, $service);
    }

    public function test_generate_returns_string_token(): void
    {
        $service = app(AccountLinkTokenService::class);
        $token = $service->generate('slack', 'U12345');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }
}
