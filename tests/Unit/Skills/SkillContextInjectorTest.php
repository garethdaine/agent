<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Services\Skills\DTOs\ResolvedSkill;
use App\Services\Skills\SkillContextInjector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SkillContextInjectorTest extends TestCase
{
    private SkillContextInjector $injector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector = new SkillContextInjector;
    }

    public function test_injects_metadata_block_with_correct_format(): void
    {
        $skills = [
            new ResolvedSkill(
                id: 'skill-1',
                name: 'compliance-check',
                description: 'Validates compliance requirements',
                version: '1.0.0',
                riskLevel: 'standard',
                skillPath: '/tmp/skills/compliance',
                triggerKeywords: ['compliance', 'regulation'],
                runAfter: [],
                score: 0.9,
            ),
            new ResolvedSkill(
                id: 'skill-2',
                name: 'code-review',
                description: 'Reviews code quality',
                version: '2.0.0',
                riskLevel: 'low',
                skillPath: '/tmp/skills/review',
                triggerKeywords: ['code', 'review', 'quality'],
                runAfter: [],
                score: 0.7,
            ),
        ];

        $existingContent = "# Task\n\nDo something important.";
        $result = $this->injector->injectMetadata($skills, $existingContent);

        $this->assertStringContainsString('## Available Skills', $result);
        $this->assertStringContainsString('### compliance-check', $result);
        $this->assertStringContainsString('Validates compliance requirements', $result);
        $this->assertStringContainsString('Keywords: compliance, regulation', $result);
        $this->assertStringContainsString('### code-review', $result);
        $this->assertStringContainsString('Reviews code quality', $result);
        $this->assertStringContainsString('Keywords: code, review, quality', $result);
        $this->assertStringContainsString('Do something important.', $result);
    }

    public function test_metadata_approximately_100_tokens_per_skill(): void
    {
        $skill = new ResolvedSkill(
            id: 'skill-1',
            name: 'test-skill',
            description: 'A test skill that does testing things for validation purposes.',
            version: '1.0.0',
            riskLevel: 'standard',
            skillPath: '/tmp/skills/test',
            triggerKeywords: ['test', 'validation', 'check'],
            runAfter: [],
            score: 0.8,
        );

        $block = $this->injector->injectMetadata([$skill], '');
        // Rough estimate: ~4 chars per token, ~100 tokens = ~400 chars per skill
        // The metadata block per skill should be under 600 chars
        $skillBlockLength = strlen($block);
        $estimatedTokens = $skillBlockLength / 4;

        $this->assertLessThan(200, $estimatedTokens, 'Single skill metadata should be approximately 100 tokens');
    }

    public function test_loads_full_instructions_from_skill_path(): void
    {
        $skillPath = sys_get_temp_dir().'/test-skill-'.uniqid();
        mkdir($skillPath, 0755, true);

        $skillMdContent = "---\nname: test-skill\nversion: \"1.0.0\"\n---\n\n# Instructions\n\nFollow these steps carefully.";
        file_put_contents($skillPath.'/SKILL.md', $skillMdContent);

        $result = $this->injector->loadFullInstructions($skillPath);

        $this->assertStringContainsString('Follow these steps carefully', $result);

        // Cleanup
        File::deleteDirectory($skillPath);
    }

    public function test_loads_resource_from_skill_path(): void
    {
        $skillPath = sys_get_temp_dir().'/test-skill-'.uniqid();
        mkdir($skillPath.'/references', 0755, true);

        file_put_contents($skillPath.'/references/guide.md', '# Reference Guide');

        $result = $this->injector->loadResource($skillPath, 'references/guide.md');

        $this->assertSame('# Reference Guide', $result);

        // Cleanup
        File::deleteDirectory($skillPath);
    }

    public function test_handles_missing_skill_path_gracefully(): void
    {
        Log::shouldReceive('warning')->once();

        $result = $this->injector->loadFullInstructions('/nonexistent/path/to/skill');

        $this->assertSame('', $result);
    }
}
