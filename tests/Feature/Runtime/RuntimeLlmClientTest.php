<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Services\Runtime\RuntimeLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeLlmClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(RuntimeLlmClient::class);
        $this->assertInstanceOf(RuntimeLlmClient::class, $service);
    }
}
