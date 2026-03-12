<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Models\MemoryCoreBlock;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\SoulResolver;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for SoulResolver personality hierarchy.
 *
 * Verifies:
 * - Specific soul takes full priority when all fields present
 * - Memory core blocks used as fallback for empty fields
 * - Field-level merge (specific personality + memory user_context)
 * - Empty/null soul falls through entirely to memory
 * - No memory returns empty defaults
 * - Memory disabled returns empty defaults
 * - Memory failures are non-blocking
 */
class SoulResolverTest extends TestCase
{
    private CoreMemoryManager&MockInterface $coreMemoryManager;

    private FeatureFlagManager&MockInterface $featureFlags;

    private SoulResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreMemoryManager = Mockery::mock(CoreMemoryManager::class);
        $this->featureFlags = Mockery::mock(FeatureFlagManager::class);

        $this->resolver = new SoulResolver(
            $this->coreMemoryManager,
            $this->featureFlags,
        );
    }

    public function test_specific_soul_takes_full_priority(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        // Memory has values but they should be overridden
        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'agent_persona')
            ->andReturn($this->makeBlock('Memory persona'));
        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'user_profile')
            ->andReturn($this->makeBlock('Memory user profile'));

        $specificSoul = [
            'name' => 'Atlas',
            'personality' => 'Formal and precise',
            'system_prompt' => 'You are a code reviewer',
            'user_context' => 'Senior developer working on Laravel',
        ];

        $result = $this->resolver->resolve($specificSoul, 1);

        $this->assertEquals('Atlas', $result['name']);
        $this->assertEquals('Formal and precise', $result['personality']);
        $this->assertEquals('You are a code reviewer', $result['system_prompt']);
        $this->assertEquals('Senior developer working on Laravel', $result['user_context']);
    }

    public function test_memory_core_blocks_used_as_fallback(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'agent_persona')
            ->andReturn($this->makeBlock('Helpful and proactive'));
        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'user_profile')
            ->andReturn($this->makeBlock('Full-stack developer'));

        // Null soul → should use memory defaults entirely
        $result = $this->resolver->resolve(null, 1);

        $this->assertNull($result['name']);
        $this->assertEquals('Helpful and proactive', $result['personality']);
        $this->assertNull($result['system_prompt']);
        $this->assertEquals('Full-stack developer', $result['user_context']);
    }

    public function test_field_level_merge_specific_personality_memory_user_context(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'agent_persona')
            ->andReturn($this->makeBlock('Memory persona'));
        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'user_profile')
            ->andReturn($this->makeBlock('Works on agent scheduler'));

        // Specific soul has personality but no user_context
        $specificSoul = [
            'name' => 'Bolt',
            'personality' => 'Fast and efficient',
            'system_prompt' => null,
            'user_context' => null,
        ];

        $result = $this->resolver->resolve($specificSoul, 1);

        $this->assertEquals('Bolt', $result['name']);
        $this->assertEquals('Fast and efficient', $result['personality']); // Specific wins
        $this->assertNull($result['system_prompt']);
        $this->assertEquals('Works on agent scheduler', $result['user_context']); // Falls back to memory
    }

    public function test_empty_soul_falls_through_to_memory(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'agent_persona')
            ->andReturn($this->makeBlock('Default persona'));
        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'user_profile')
            ->andReturn($this->makeBlock('Default profile'));

        // Empty soul with no meaningful content
        $emptySoul = [
            'name' => null,
            'personality' => '',
            'system_prompt' => null,
            'user_context' => '',
        ];

        $result = $this->resolver->resolve($emptySoul, 1);

        $this->assertNull($result['name']);
        $this->assertEquals('Default persona', $result['personality']);
        $this->assertNull($result['system_prompt']);
        $this->assertEquals('Default profile', $result['user_context']);
    }

    public function test_no_memory_returns_empty_defaults(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'agent_persona')
            ->andReturn(null);
        $this->coreMemoryManager->shouldReceive('get')
            ->with(1, 'user_profile')
            ->andReturn(null);

        $result = $this->resolver->resolve(null, 1);

        $this->assertNull($result['name']);
        $this->assertNull($result['personality']);
        $this->assertNull($result['system_prompt']);
        $this->assertNull($result['user_context']);
    }

    public function test_memory_disabled_returns_empty_defaults(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(false);

        // CoreMemoryManager should not be called at all
        $this->coreMemoryManager->shouldNotReceive('get');

        $result = $this->resolver->resolve(null, 1);

        $this->assertNull($result['name']);
        $this->assertNull($result['personality']);
        $this->assertNull($result['system_prompt']);
        $this->assertNull($result['user_context']);
    }

    public function test_memory_failure_is_non_blocking(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('get')
            ->andThrow(new \RuntimeException('Database connection lost'));

        // Should not throw — returns empty defaults
        $result = $this->resolver->resolve(null, 1);

        $this->assertNull($result['personality']);
        $this->assertNull($result['user_context']);
    }

    public function test_specific_soul_with_memory_disabled(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(false);

        $specificSoul = [
            'name' => 'Atlas',
            'personality' => 'Precise and methodical',
            'system_prompt' => null,
            'user_context' => null,
        ];

        $result = $this->resolver->resolve($specificSoul, 1);

        // Specific soul fields still used, empty fields remain null (no memory fallback)
        $this->assertEquals('Atlas', $result['name']);
        $this->assertEquals('Precise and methodical', $result['personality']);
        $this->assertNull($result['user_context']);
    }

    private function makeBlock(string $content): MemoryCoreBlock
    {
        $block = new MemoryCoreBlock;
        $block->content_text = $content;

        return $block;
    }
}
