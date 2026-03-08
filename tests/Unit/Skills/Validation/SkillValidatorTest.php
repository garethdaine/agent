<?php

declare(strict_types=1);

namespace Tests\Unit\Skills\Validation;

use App\Services\Skills\DTOs\SkillParseResult;
use App\Services\Skills\SkillValidator;
use App\Services\Skills\Validation\CodeSafetyValidator;
use App\Services\Skills\Validation\ContentAnalysisValidator;
use App\Services\Skills\Validation\FormatValidator;
use App\Services\Skills\Validation\IntegrityValidator;
use App\Services\Skills\Validation\SchemaValidator;
use App\Services\Skills\Validation\SkillValidationResult;
use App\Services\Skills\Validation\StageResult;
use Mockery;
use Tests\TestCase;

class SkillValidatorTest extends TestCase
{
    public function test_orchestrates_all_5_stages(): void
    {
        $extractedPath = base_path('tests/Fixtures/Skills/valid-skill');
        $parsed = $this->makeParseResult();

        $format = Mockery::mock(FormatValidator::class);
        $format->shouldReceive('validate')->once()->andReturn(StageResult::pass('format', 5));

        $schema = Mockery::mock(SchemaValidator::class);
        $schema->shouldReceive('validate')->once()->andReturn(StageResult::pass('schema', 8));

        $content = Mockery::mock(ContentAnalysisValidator::class);
        $content->shouldReceive('validate')->once()->andReturn(
            StageResult::pass('content_analysis', 4, [
                'weighted_score' => 0.05,
                'classification' => 'clean',
                'scores' => [],
                'used_llm' => false,
            ])
        );

        $codeSafety = Mockery::mock(CodeSafetyValidator::class);
        $codeSafety->shouldReceive('validate')->once()->andReturn(StageResult::pass('code_safety', 1));

        $integrity = Mockery::mock(IntegrityValidator::class);
        $integrity->shouldReceive('validate')->once()->andReturn(
            StageResult::pass('integrity', 1, ['file_hashes' => ['SKILL.md' => 'abc123']])
        );

        $validator = new SkillValidator($format, $schema, $content, $codeSafety, $integrity);

        $result = $validator->validate($extractedPath, $parsed, 'upload', 1);

        $this->assertInstanceOf(SkillValidationResult::class, $result);
        $this->assertTrue($result->overallPass);
        $this->assertCount(5, $result->stages);
        $this->assertArrayHasKey('format', $result->stages);
        $this->assertArrayHasKey('schema', $result->stages);
        $this->assertArrayHasKey('content_analysis', $result->stages);
        $this->assertArrayHasKey('code_safety', $result->stages);
        $this->assertArrayHasKey('integrity', $result->stages);
    }

    public function test_short_circuits_on_format_block(): void
    {
        $extractedPath = base_path('tests/Fixtures/Skills/valid-skill');
        $parsed = $this->makeParseResult();

        $format = Mockery::mock(FormatValidator::class);
        $format->shouldReceive('validate')->once()->andReturn(
            StageResult::fail('format', [[
                'check' => 'skill_md_exists',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'SKILL.md not found.',
            ]])
        );

        // These should NOT be called due to short-circuit
        $schema = Mockery::mock(SchemaValidator::class);
        $schema->shouldNotReceive('validate');

        $content = Mockery::mock(ContentAnalysisValidator::class);
        $content->shouldNotReceive('validate');

        $codeSafety = Mockery::mock(CodeSafetyValidator::class);
        $codeSafety->shouldNotReceive('validate');

        $integrity = Mockery::mock(IntegrityValidator::class);
        $integrity->shouldNotReceive('validate');

        $validator = new SkillValidator($format, $schema, $content, $codeSafety, $integrity);

        $result = $validator->validate($extractedPath, $parsed, 'upload', 1);

        $this->assertFalse($result->overallPass);
        $this->assertCount(1, $result->stages);
        $this->assertNotEmpty($result->blockingErrors);
    }

    public function test_returns_complete_validation_result(): void
    {
        $extractedPath = base_path('tests/Fixtures/Skills/valid-skill');
        $parsed = $this->makeParseResult();

        $fileHashes = ['SKILL.md' => hash('sha256', 'test')];

        $format = Mockery::mock(FormatValidator::class);
        $format->shouldReceive('validate')->andReturn(StageResult::pass('format'));

        $schema = Mockery::mock(SchemaValidator::class);
        $schema->shouldReceive('validate')->andReturn(
            StageResult::warn('schema', ['Body exceeds recommended length.'])
        );

        $content = Mockery::mock(ContentAnalysisValidator::class);
        $content->shouldReceive('validate')->andReturn(
            StageResult::pass('content_analysis', 4, [
                'weighted_score' => 0.05,
                'classification' => 'clean',
                'scores' => [],
                'used_llm' => false,
            ])
        );

        $codeSafety = Mockery::mock(CodeSafetyValidator::class);
        $codeSafety->shouldReceive('validate')->andReturn(StageResult::pass('code_safety'));

        $integrity = Mockery::mock(IntegrityValidator::class);
        $integrity->shouldReceive('validate')->andReturn(
            StageResult::pass('integrity', 1, ['file_hashes' => $fileHashes])
        );

        $validator = new SkillValidator($format, $schema, $content, $codeSafety, $integrity);

        $result = $validator->validate($extractedPath, $parsed, 'upload', 1);

        $this->assertTrue($result->overallPass);
        $this->assertSame(0.05, $result->riskScore);
        $this->assertCount(5, $result->stages);
        $this->assertNotEmpty($result->warnings);
        $this->assertEmpty($result->blockingErrors);
        $this->assertSame($fileHashes, $result->fileHashes);

        // Test toArray
        $array = $result->toArray();
        $this->assertArrayHasKey('overall_pass', $array);
        $this->assertArrayHasKey('risk_score', $array);
        $this->assertArrayHasKey('stages', $array);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertArrayHasKey('blocking_errors', $array);
        $this->assertArrayHasKey('file_hashes', $array);
    }

    private function makeParseResult(): SkillParseResult
    {
        return new SkillParseResult(
            name: 'test-skill',
            description: 'A valid test skill description for the orchestrator test.',
            version: '1.0.0',
            author: 'testops',
            license: 'MIT',
            riskLevel: 'standard',
            industries: ['technology'],
            memoryBlocks: [],
            mcpDependencies: [],
            toolsRequired: [],
            triggerKeywords: ['test'],
            runAfter: [],
            requiresApproval: false,
            compatibility: null,
            markdownBody: "# Test\n\nBody content.",
            warnings: [],
            errors: [],
            rawFrontmatter: ['name' => 'test-skill'],
        );
    }
}
