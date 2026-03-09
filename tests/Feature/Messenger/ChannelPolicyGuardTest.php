<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Services\Messenger\ChannelPolicyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelPolicyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ChannelPolicyGuard::class);
        $this->assertInstanceOf(ChannelPolicyGuard::class, $service);
    }
}
