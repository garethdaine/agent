<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Models\User;
use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\HybridRetriever;
use App\Support\Memory\MemoryContextInjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for MemoryContextInjector.
 *
 * Verifies:
 * - Injection appends memory context to system prompt
 * - Feature flag disabled returns original prompt
 * - Failure is non-blocking (returns original prompt)
 * - Truncation works correctly
 * - Empty memory returns original prompt unchanged
 * - Core blocks are included in injected context
 * - Hybrid retriever results are included when taskContext provided
 */
class MemoryContextInjectorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CoreMemoryManager $coreMemoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);

        $this->user = User::factory()->create();
        $this->coreMemoryManager = app(CoreMemoryManager::class);
    }

    /**
     * Test that injection appends memory context to system prompt.
     */
    public function test_injection_appends_context_to_prompt(): void
    {
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            'Helpful coding assistant.',
            null,
            ['actor' => 'user']
        );

        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')->never();

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $original = 'You are a system analyst.';
        $result = $injector->inject($original, $this->user->id);

        $this->assertStringContainsString('You are a system analyst.', $result);
        $this->assertStringContainsString('## Relevant Memory Context', $result);
        $this->assertStringContainsString('Helpful coding assistant.', $result);
    }

    /**
     * Test that feature flag disabled returns original prompt.
     */
    public function test_returns_original_when_memory_disabled(): void
    {
        config(['memory.enabled' => false]);

        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')->never();

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $original = 'You are a system analyst.';
        $result = $injector->inject($original, $this->user->id);

        $this->assertEquals($original, $result);
    }

    /**
     * Test that failure is non-blocking.
     */
    public function test_failure_returns_original_prompt(): void
    {
        $mockCoreMemory = Mockery::mock(CoreMemoryManager::class);
        $mockCoreMemory->shouldReceive('get')
            ->andThrow(new \RuntimeException('Database error'));

        $mockRetriever = Mockery::mock(HybridRetriever::class);

        $injector = new MemoryContextInjector($mockCoreMemory, $mockRetriever);

        $original = 'You are a system analyst.';
        $result = $injector->inject($original, $this->user->id);

        $this->assertEquals($original, $result);
    }

    /**
     * Test truncation works correctly.
     */
    public function test_truncates_to_max_chars(): void
    {
        // Create a long persona
        $longPersona = str_repeat('This is a very long persona. ', 100);
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            $longPersona,
            null,
            ['actor' => 'user']
        );

        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')->never();

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $original = 'Base prompt.';
        $result = $injector->inject($original, $this->user->id, null, maxChars: 100);

        // Memory section should be truncated
        $memorySection = substr($result, strlen($original));
        // The injected section (after "## Relevant Memory Context\n\n") should respect maxChars
        $this->assertStringContainsString('...', $result);
    }

    /**
     * Test empty memory returns original prompt unchanged.
     */
    public function test_empty_memory_returns_original(): void
    {
        // No core blocks created
        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')->never();

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $original = 'You are a system analyst.';
        $result = $injector->inject($original, $this->user->id);

        $this->assertEquals($original, $result);
    }

    /**
     * Test that both agent_persona and user_profile core blocks are included.
     */
    public function test_includes_both_core_blocks(): void
    {
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            'Helpful assistant.',
            null,
            ['actor' => 'user']
        );
        $this->coreMemoryManager->set(
            $this->user->id,
            'user_profile',
            'Senior developer.',
            null,
            ['actor' => 'user']
        );

        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')->never();

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $result = $injector->inject('Base prompt.', $this->user->id);

        $this->assertStringContainsString('**Agent Identity:**', $result);
        $this->assertStringContainsString('Helpful assistant.', $result);
        $this->assertStringContainsString('**User Context:**', $result);
        $this->assertStringContainsString('Senior developer.', $result);
    }

    /**
     * Test that HybridRetriever results are included when taskContext is provided.
     */
    public function test_includes_retriever_results_with_task_context(): void
    {
        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')
            ->with($this->user->id, 'authentication flow', Mockery::type('array'))
            ->once()
            ->andReturn([
                [
                    'id' => 1,
                    'content' => 'Previously implemented OAuth2 flow.',
                    'source' => 'keyword',
                    'classification' => 'internal',
                    'rrf_score' => 0.5,
                ],
            ]);

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $result = $injector->inject('Base prompt.', $this->user->id, 'authentication flow');

        $this->assertStringContainsString('**Relevant Context:**', $result);
        $this->assertStringContainsString('Previously implemented OAuth2 flow.', $result);
    }

    /**
     * Test that retriever failure does not prevent core block injection.
     */
    public function test_retriever_failure_still_includes_core_blocks(): void
    {
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            'Helpful assistant.',
            null,
            ['actor' => 'user']
        );

        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')
            ->andThrow(new \RuntimeException('Search failed'));

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $result = $injector->inject('Base prompt.', $this->user->id, 'some query');

        // Core blocks should still be included even if retriever fails
        $this->assertStringContainsString('Helpful assistant.', $result);
    }

    /**
     * Test that null taskContext skips retriever call.
     */
    public function test_null_task_context_skips_retriever(): void
    {
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            'Test persona.',
            null,
            ['actor' => 'user']
        );

        $mockRetriever = Mockery::mock(HybridRetriever::class);
        $mockRetriever->shouldReceive('retrieve')->never();

        $injector = new MemoryContextInjector($this->coreMemoryManager, $mockRetriever);

        $result = $injector->inject('Base prompt.', $this->user->id, null);

        $this->assertStringContainsString('Test persona.', $result);
    }
}
