<?php

declare(strict_types=1);

namespace Tests\Feature\Replay;

use App\Services\Replay\ReplayParityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplayParityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(ReplayParityService::class);
        $this->assertInstanceOf(ReplayParityService::class, $service);
    }
}
