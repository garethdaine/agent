<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Services\Messenger\ChatResponseFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatResponseFormatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ChatResponseFormatter::class);
        $this->assertInstanceOf(ChatResponseFormatter::class, $service);
    }
}
