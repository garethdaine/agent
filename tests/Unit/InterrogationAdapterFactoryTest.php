<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\Adapters\CodexAdapter;
use App\Support\Interrogation\Adapters\CustomAdapter;
use Tests\TestCase;

class InterrogationAdapterFactoryTest extends TestCase
{
    public function test_factory_supports_all_workflow_interrogator_runner_types(): void
    {
        $factory = app(AdapterFactory::class);

        $this->assertInstanceOf(ClaudeAdapter::class, $factory->make('claude'));
        $this->assertInstanceOf(CodexAdapter::class, $factory->make('codex'));
        $this->assertInstanceOf(CustomAdapter::class, $factory->make('custom'));
    }
}
