<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Services\Messenger\AgentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRouterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(AgentRouter::class);
        $this->assertInstanceOf(AgentRouter::class, $service);
    }
}
