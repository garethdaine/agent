<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Observability;

use App\Messenger\Observability\CorrelationContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Tests\TestCase;

class CorrelationContextTest extends TestCase
{
    private CorrelationContext $correlationContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing context
        Context::flush();

        $this->correlationContext = new CorrelationContext;
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    public function test_generate_creates_new_uuid(): void
    {
        $id = $this->correlationContext->generate();

        $this->assertNotNull($id);
        $this->assertTrue(Str::isUuid($id));
    }

    public function test_get_returns_current_id(): void
    {
        $generatedId = $this->correlationContext->generate();

        $retrievedId = $this->correlationContext->get();

        $this->assertEquals($generatedId, $retrievedId);
    }

    public function test_get_returns_null_when_no_id_set(): void
    {
        $id = $this->correlationContext->get();

        $this->assertNull($id);
    }

    public function test_reset_clears_and_generates_new_id(): void
    {
        $originalId = $this->correlationContext->generate();
        $newId = $this->correlationContext->reset();

        $this->assertNotEquals($originalId, $newId);
        $this->assertTrue(Str::isUuid($newId));
        $this->assertEquals($newId, $this->correlationContext->get());
    }

    public function test_id_persists_across_calls_within_same_context(): void
    {
        $id1 = $this->correlationContext->generate();
        $id2 = $this->correlationContext->get();
        $id3 = $this->correlationContext->get();

        $this->assertEquals($id1, $id2);
        $this->assertEquals($id2, $id3);
    }

    public function test_id_available_in_log_context(): void
    {
        $id = $this->correlationContext->generate();

        // Laravel Context data is available via Context::all()
        $contextData = Context::all();

        $this->assertArrayHasKey('messenger_correlation_id', $contextData);
        $this->assertEquals($id, $contextData['messenger_correlation_id']);
    }

    public function test_get_or_generate_returns_existing_id_if_set(): void
    {
        $existingId = $this->correlationContext->generate();

        $retrievedId = $this->correlationContext->getOrGenerate();

        $this->assertEquals($existingId, $retrievedId);
    }

    public function test_get_or_generate_creates_new_id_if_not_set(): void
    {
        $id = $this->correlationContext->getOrGenerate();

        $this->assertNotNull($id);
        $this->assertTrue(Str::isUuid($id));
        $this->assertEquals($id, $this->correlationContext->get());
    }

    public function test_multiple_generates_overwrite_previous_id(): void
    {
        $id1 = $this->correlationContext->generate();
        $id2 = $this->correlationContext->generate();

        $this->assertNotEquals($id1, $id2);
        $this->assertEquals($id2, $this->correlationContext->get());
    }

    public function test_instance_can_be_resolved_from_container(): void
    {
        $instance = app(CorrelationContext::class);

        $this->assertInstanceOf(CorrelationContext::class, $instance);
    }
}
