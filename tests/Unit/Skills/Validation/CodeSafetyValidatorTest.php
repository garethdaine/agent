<?php

namespace Tests\Unit\Skills\Validation;

use App\Services\Skills\Validation\CodeSafetyValidator;
use App\Services\Skills\Validation\StageResult;
use Tests\TestCase;

class CodeSafetyValidatorTest extends TestCase
{
    private CodeSafetyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CodeSafetyValidator;
    }

    public function test_passes_no_scripts(): void
    {
        $path = base_path('tests/Fixtures/Skills/valid-skill');

        $result = $this->validator->validate($path);

        $this->assertTrue($result->passed);
        $this->assertSame('code_safety', $result->name);
        $this->assertEmpty($result->failures);
    }

    public function test_passes_safe_scripts(): void
    {
        $tempDir = $this->createTempSkill([
            'scripts/helper.php' => "<?php\nfunction add(\$a, \$b) { return \$a + \$b; }\n",
        ]);

        $result = $this->validator->validate($tempDir);

        $this->recursiveDelete($tempDir);

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->failures);
    }

    public function test_fails_dangerous_php_functions(): void
    {
        $path = base_path('tests/Fixtures/Skills/dangerous-scripts');

        $result = $this->validator->validate($path);

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasBlockingFailures());

        $dangerousFailure = collect($result->failures)->firstWhere('check', 'dangerous_functions');
        $this->assertNotNull($dangerousFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $dangerousFailure['severity']);
    }

    public function test_fails_binary_executable(): void
    {
        $path = base_path('tests/Fixtures/Skills/binary-file');

        $result = $this->validator->validate($path);

        $this->assertFalse($result->passed);

        // The .exe extension is not in the allowlist, so it triggers language_allowlist
        $disallowedFailure = collect($result->failures)->first(function ($f) {
            return $f['check'] === 'language_allowlist' || $f['check'] === 'binary_check';
        });
        $this->assertNotNull($disallowedFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $disallowedFailure['severity']);
    }

    public function test_fails_undeclared_network_calls(): void
    {
        $path = base_path('tests/Fixtures/Skills/dangerous-scripts');

        $result = $this->validator->validate($path);

        $this->assertFalse($result->passed);

        $networkFailure = collect($result->failures)->firstWhere('check', 'network_calls');
        $this->assertNotNull($networkFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $networkFailure['severity']);
    }

    public function test_fails_filesystem_escape(): void
    {
        $tempDir = $this->createTempSkill([
            'scripts/escape.py' => "import os\npath = '../../etc/passwd'\ndata = open(path).read()\n",
        ]);

        $result = $this->validator->validate($tempDir);

        $this->recursiveDelete($tempDir);

        $this->assertFalse($result->passed);

        $escapeFailure = collect($result->failures)->firstWhere('check', 'filesystem_escape');
        $this->assertNotNull($escapeFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $escapeFailure['severity']);
    }

    public function test_fails_disallowed_language(): void
    {
        $tempDir = $this->createTempSkill([
            'scripts/code.rb' => "puts 'Hello from Ruby'\n",
        ]);

        $result = $this->validator->validate($tempDir);

        $this->recursiveDelete($tempDir);

        $this->assertFalse($result->passed);

        $langFailure = collect($result->failures)->firstWhere('check', 'language_allowlist');
        $this->assertNotNull($langFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $langFailure['severity']);
        $this->assertStringContainsString('.rb', $langFailure['message']);
    }

    private function createTempSkill(array $files): string
    {
        $tempDir = sys_get_temp_dir().'/skill-safety-test-'.uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir.'/SKILL.md', "---\nname: test\n---\nBody");

        foreach ($files as $path => $content) {
            $fullPath = $tempDir.'/'.$path;
            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($fullPath, $content);
        }

        return $tempDir;
    }

    private function recursiveDelete(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
