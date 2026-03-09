<?php

declare(strict_types=1);

namespace Tests\Feature\Credentials;

use App\Services\Credentials\OAuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(OAuthTokenService::class);
        $this->assertInstanceOf(OAuthTokenService::class, $service);
    }
}
