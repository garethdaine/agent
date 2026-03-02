<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\HybridRetriever;
use App\Support\Memory\MemoryContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * Tests for MemoryContextBuilder wrapper markdown generation.
 *
 * Verifies:
 * - Generates wrapper markdown with agent_persona section
 * - Includes user_profile section
 * - Includes relevant memories from HybridRetriever
 * - Calculates token budget correctly (5% - 10% margin)
 * - Clamps to floor 1000, ceiling 8000
 * - Truncates context to budget
 * - Stores file in correct path pattern
 */
class MemoryContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AgentJob $job;

    private AgentJobRun $run;

    private CoreMemoryManager $coreMemoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);

        $this->user = User::factory()->create();
        $this->job = AgentJob::factory()->for($this->user)->create([
            'task_markdown_path' => '/tmp/test_task.md',
        ]);
        $this->run = AgentJobRun::factory()->for($this->job, 'job')->create();

        // Create test task file
        File::put('/tmp/test_task.md', "# Original Task\n\nDo something.");

        $this->coreMemoryManager = app(CoreMemoryManager::class);
    }

    protected function tearDown(): void
    {
        File::delete('/tmp/test_task.md');

        // Clean up any generated context files
        $contextDir = storage_path('app/memory/context');
        if (File::isDirectory($contextDir)) {
            File::deleteDirectory($contextDir);
        }

        parent::tearDown();
    }

    /**
     * Test that wrapper markdown includes agent_persona section.
     */
    public function test_generates_wrapper_with_agent_persona_section(): void
    {
        // Create agent persona block
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            'I am a helpful coding assistant.',
            null,
            ['actor' => 'user']
        );

        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $this->assertFileExists($contextPath);

        $content = File::get($contextPath);
        $this->assertStringContainsString('## Agent Identity', $content);
        $this->assertStringContainsString('I am a helpful coding assistant.', $content);
    }

    /**
     * Test that wrapper markdown includes user_profile section.
     */
    public function test_generates_wrapper_with_user_profile_section(): void
    {
        // Create user profile block
        $this->coreMemoryManager->set(
            $this->user->id,
            'user_profile',
            'Senior PHP developer working on Laravel projects.',
            null,
            ['actor' => 'user']
        );

        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $content = File::get($contextPath);
        $this->assertStringContainsString('## User Context', $content);
        $this->assertStringContainsString('Senior PHP developer working on Laravel projects.', $content);
    }

    /**
     * Test that wrapper includes relevant memories from HybridRetriever.
     */
    public function test_includes_relevant_memories_from_hybrid_retriever(): void
    {
        $memories = [
            [
                'id' => 1,
                'content' => 'Previously discussed database migrations.',
                'source' => 'keyword',
                'classification' => 'internal',
                'rrf_score' => 0.5,
            ],
            [
                'id' => 2,
                'content' => 'Worked on API authentication last week.',
                'source' => 'semantic',
                'classification' => 'internal',
                'rrf_score' => 0.3,
            ],
        ];

        $builder = $this->createBuilderWithMockedRetriever($memories);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $content = File::get($contextPath);
        $this->assertStringContainsString('## Relevant Memories', $content);
        $this->assertStringContainsString('Previously discussed database migrations.', $content);
        $this->assertStringContainsString('Worked on API authentication last week.', $content);
    }

    /**
     * Test token budget calculation: 5% of context window with 10% margin.
     */
    public function test_calculates_token_budget_correctly(): void
    {
        // Default context window: 200000 tokens
        // 5% of 200000 = 10000 tokens
        // 10% margin = 10000 * 0.9 = 9000 tokens
        // Clamped to ceiling 8000

        config([
            'memory.context_injection.budget_percent' => 5,
            'memory.context_injection.margin_percent' => 10,
            'memory.context_injection.floor_tokens' => 1000,
            'memory.context_injection.ceiling_tokens' => 8000,
            'memory.context_injection.default_context_window' => 200000,
        ]);

        $builder = $this->createBuilderWithMockedRetriever([]);

        $budget = $builder->calculateTokenBudget();

        $this->assertEquals(8000, $budget); // Clamped to ceiling
    }

    /**
     * Test that token budget respects floor of 1000.
     */
    public function test_clamps_to_floor_1000(): void
    {
        // Small context window: 10000 tokens
        // 5% of 10000 = 500 tokens
        // 10% margin = 500 * 0.9 = 450 tokens
        // Clamped to floor 1000

        config([
            'memory.context_injection.budget_percent' => 5,
            'memory.context_injection.margin_percent' => 10,
            'memory.context_injection.floor_tokens' => 1000,
            'memory.context_injection.ceiling_tokens' => 8000,
            'memory.context_injection.default_context_window' => 10000,
        ]);

        $builder = $this->createBuilderWithMockedRetriever([]);

        $budget = $builder->calculateTokenBudget();

        $this->assertEquals(1000, $budget); // Clamped to floor
    }

    /**
     * Test that token budget clamps to ceiling of 8000.
     */
    public function test_clamps_to_ceiling_8000(): void
    {
        // Large context window: 500000 tokens
        // 5% of 500000 = 25000 tokens
        // 10% margin = 25000 * 0.9 = 22500 tokens
        // Clamped to ceiling 8000

        config([
            'memory.context_injection.budget_percent' => 5,
            'memory.context_injection.margin_percent' => 10,
            'memory.context_injection.floor_tokens' => 1000,
            'memory.context_injection.ceiling_tokens' => 8000,
            'memory.context_injection.default_context_window' => 500000,
        ]);

        $builder = $this->createBuilderWithMockedRetriever([]);

        $budget = $builder->calculateTokenBudget();

        $this->assertEquals(8000, $budget);
    }

    /**
     * Test that context is truncated to token budget.
     */
    public function test_truncates_context_to_budget(): void
    {
        // Create a very long memory that exceeds budget
        $longContent = str_repeat('This is a very long memory entry. ', 500);
        $memories = [
            [
                'id' => 1,
                'content' => $longContent,
                'source' => 'keyword',
                'classification' => 'internal',
                'rrf_score' => 0.5,
            ],
        ];

        config([
            'memory.context_injection.budget_percent' => 5,
            'memory.context_injection.margin_percent' => 10,
            'memory.context_injection.floor_tokens' => 1000,
            'memory.context_injection.ceiling_tokens' => 2000, // Small ceiling for test
            'memory.context_injection.chars_per_token' => 4,
            'memory.context_injection.default_context_window' => 200000,
        ]);

        $builder = $this->createBuilderWithMockedRetriever($memories);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $content = File::get($contextPath);

        // Budget is 2000 tokens * 4 chars = 8000 chars max for memory section
        // Original task content should always be included at the end
        $this->assertStringContainsString('# Original Task', $content);

        // Verify the memory content is truncated (not all of it is included)
        $this->assertLessThan(strlen($longContent), strlen($content));
    }

    /**
     * Test that file is stored in correct path pattern.
     */
    public function test_stores_file_in_correct_path_pattern(): void
    {
        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);

        // Path should be: storage/app/memory/context/{YYYY-MM-DD}/{uuid}.md
        $this->assertStringContainsString(storage_path('app/memory/context'), $contextPath);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $contextPath);
        $this->assertStringEndsWith('.md', $contextPath);
    }

    /**
     * Test that original task content is included at the end.
     */
    public function test_includes_original_task_content_at_end(): void
    {
        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $content = File::get($contextPath);

        // Original task should be at the end after separator
        $this->assertStringContainsString('---', $content);
        $this->assertStringContainsString('# Original Task', $content);
        $this->assertStringContainsString('Do something.', $content);

        // Original task should come after the separator
        $separatorPos = strpos($content, '---');
        $originalTaskPos = strpos($content, '# Original Task');
        $this->assertGreaterThan($separatorPos, $originalTaskPos);
    }

    /**
     * Test that wrapper handles missing core memory blocks gracefully.
     */
    public function test_handles_missing_core_memory_blocks(): void
    {
        // Don't create any core memory blocks
        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $content = File::get($contextPath);

        // Should still generate valid wrapper with empty sections or omitted
        $this->assertStringContainsString('---', $content);
        $this->assertStringContainsString('# Original Task', $content);
    }

    /**
     * Test that wrapper handles empty retriever results.
     */
    public function test_handles_empty_retriever_results(): void
    {
        $this->coreMemoryManager->set(
            $this->user->id,
            'agent_persona',
            'Test persona',
            null,
            ['actor' => 'user']
        );

        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNotNull($contextPath);
        $content = File::get($contextPath);

        // Should still include agent persona and original task
        $this->assertStringContainsString('Test persona', $content);
        $this->assertStringContainsString('# Original Task', $content);
    }

    /**
     * Test that returns null when memory is disabled.
     */
    public function test_returns_null_when_memory_disabled(): void
    {
        config(['memory.enabled' => false]);

        $builder = $this->createBuilderWithMockedRetriever([]);

        $contextPath = $builder->buildContext($this->run);

        $this->assertNull($contextPath);
    }

    /**
     * Create a MemoryContextBuilder with a mocked HybridRetriever.
     *
     * @param  array<array{id: int|string, content: string, source: string, classification: string, rrf_score: float}>  $memories
     */
    private function createBuilderWithMockedRetriever(array $memories): MemoryContextBuilder
    {
        // Mock the HybridRetriever
        $retriever = Mockery::mock(HybridRetriever::class);
        $retriever->shouldReceive('retrieve')
            ->andReturn($memories);

        return new MemoryContextBuilder(
            $this->coreMemoryManager,
            $retriever
        );
    }
}
