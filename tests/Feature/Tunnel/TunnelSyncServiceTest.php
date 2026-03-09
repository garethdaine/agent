<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Services\Tunnel\TunnelSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(TunnelSyncService::class);
        $this->assertInstanceOf(TunnelSyncService::class, $service);
    }
}
