<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Services\Messenger\ChatSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSessionManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ChatSessionManager::class);
        $this->assertInstanceOf(ChatSessionManager::class, $service);
    }
}
