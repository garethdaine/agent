<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Services\Messenger\ConfirmationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ConfirmationManager::class);
        $this->assertInstanceOf(ConfirmationManager::class, $service);
    }
}
