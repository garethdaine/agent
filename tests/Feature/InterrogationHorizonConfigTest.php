<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class InterrogationHorizonConfigTest extends TestCase
{
    public function test_interrogation_queue_supervisor_exists(): void
    {
        $this->assertSame(30, config('horizon.waits.redis:interrogation'));
        $this->assertSame(['interrogation'], config('horizon.defaults.supervisor-interrogation.queue'));
        $this->assertSame(7800, config('horizon.defaults.supervisor-interrogation.timeout'));
        $this->assertSame(1, config('horizon.defaults.supervisor-interrogation.tries'));
    }
}
