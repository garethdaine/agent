<?php

namespace Tests\Unit\Skills\Validation;

use App\Models\AgentSkill;
use App\Services\Skills\DTOs\SkillParseResult;
use App\Services\Skills\Validation\SchemaValidator;
use App\Services\Skills\Validation\StageResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SchemaValidator;
    }

    public function test_passes_valid_parse_result(): void
    {
        $parsed = $this->makeParseResult();

        $result = $this->validator->validate($parsed, 1);

        $this->assertTrue($result->passed);
        $this->assertSame('schema', $result->name);
        $this->assertEmpty($result->failures);
    }

    public function test_fails_missing_name(): void
    {
        $parsed = $this->makeParseResult(name: null);

        $result = $this->validator->validate($parsed, 1);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasBlockingFailures());

        $nameFailure = collect($result->failures)->firstWhere('check', 'name_required');
        $this->assertNotNull($nameFailure);
    }

    public function test_fails_invalid_name_format(): void
    {
        $parsed = $this->makeParseResult(name: 'InvalidUpperCase');

        $result = $this->validator->validate($parsed, 1);

        $this->assertFalse($result->passed);

        $formatFailure = collect($result->failures)->firstWhere('check', 'name_format');
        $this->assertNotNull($formatFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $formatFailure['severity']);
    }

    public function test_fails_duplicate_slug(): void
    {
        $skill = AgentSkill::factory()->create([
            'slug' => 'test-skill',
        ]);

        $parsed = $this->makeParseResult(name: 'test-skill');

        $result = $this->validator->validate($parsed, $skill->team_id);

        $this->assertFalse($result->passed);

        $duplicateFailure = collect($result->failures)->firstWhere('check', 'duplicate_slug');
        $this->assertNotNull($duplicateFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $duplicateFailure['severity']);
    }

    public function test_warns_invalid_risk_level(): void
    {
        $parsed = $this->makeParseResult(riskLevel: 'unknown-level');

        $result = $this->validator->validate($parsed, 1);

        $this->assertTrue($result->passed);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('Unknown risk level', $result->warnings[0]);
    }

    public function test_warns_long_body(): void
    {
        $longBody = str_repeat("Line of content\n", 600);
        $parsed = $this->makeParseResult(markdownBody: $longBody);

        $result = $this->validator->validate($parsed, 1);

        $this->assertTrue($result->passed);
        $this->assertNotEmpty($result->warnings);

        $bodyWarning = collect($result->warnings)->first(
            fn (string $w) => str_contains($w, 'lines')
        );
        $this->assertNotNull($bodyWarning);
    }

    public function test_warns_unknown_x_agent_keys(): void
    {
        $parsed = $this->makeParseResult(rawFrontmatter: [
            'name' => 'test-skill',
            'x-agent' => [
                'industries' => [],
                'custom_unknown_key' => 'value',
                'another_unknown' => true,
            ],
        ]);

        $result = $this->validator->validate($parsed, 1);

        $this->assertTrue($result->passed);
        $this->assertNotEmpty($result->warnings);

        $unknownWarning = collect($result->warnings)->first(
            fn (string $w) => str_contains($w, 'Unknown x-agent keys')
        );
        $this->assertNotNull($unknownWarning);
    }

    private function makeParseResult(
        ?string $name = 'test-skill',
        ?string $description = 'A valid test skill description that meets the minimum length requirement.',
        ?string $version = '1.0.0',
        ?string $author = 'testops',
        string $riskLevel = 'standard',
        string $markdownBody = "# Test Skill\n\nThis is a test skill body.",
        array $rawFrontmatter = [],
    ): SkillParseResult {
        return new SkillParseResult(
            name: $name,
            description: $description,
            version: $version,
            author: $author,
            license: 'MIT',
            riskLevel: $riskLevel,
            industries: ['technology'],
            memoryBlocks: [],
            mcpDependencies: [],
            toolsRequired: [],
            triggerKeywords: ['test'],
            runAfter: [],
            requiresApproval: false,
            compatibility: null,
            markdownBody: $markdownBody,
            warnings: [],
            errors: [],
            rawFrontmatter: $rawFrontmatter ?: ['name' => $name ?? 'test-skill'],
        );
    }
}
