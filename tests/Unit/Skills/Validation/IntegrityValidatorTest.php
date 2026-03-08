<?php

declare(strict_types=1);

namespace Tests\Unit\Skills\Validation;

use App\Services\Skills\Validation\IntegrityValidator;
use App\Services\Skills\Validation\StageResult;
use Tests\TestCase;

class IntegrityValidatorTest extends TestCase
{
    private IntegrityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new IntegrityValidator;
    }

    public function test_generates_file_hashes(): void
    {
        $path = base_path('tests/Fixtures/Skills/valid-skill');

        $result = $this->validator->validate($path, null, 'upload');

        $this->assertTrue($result->passed);
        $this->assertArrayHasKey('file_hashes', $result->details);
        $this->assertNotEmpty($result->details['file_hashes']);

        // Verify SHA-256 format (64 hex chars)
        foreach ($result->details['file_hashes'] as $filePath => $hash) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
            $this->assertIsString($filePath);
        }
    }

    public function test_checksum_match_passes(): void
    {
        $path = base_path('tests/Fixtures/Skills/valid-skill');

        // First, compute the expected checksum
        $initialResult = $this->validator->validate($path, null, 'upload');
        $fileHashes = $initialResult->details['file_hashes'];

        // Compute the overall hash the same way IntegrityValidator does
        $combined = '';
        ksort($fileHashes);
        foreach ($fileHashes as $filePath => $hash) {
            $combined .= $filePath.':'.$hash."\n";
        }
        $expectedChecksum = hash('sha256', $combined);

        // Now validate with matching checksum as library source
        $result = $this->validator->validate($path, $expectedChecksum, 'library');

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->failures);
    }

    public function test_checksum_mismatch_blocks(): void
    {
        $path = base_path('tests/Fixtures/Skills/valid-skill');

        $result = $this->validator->validate($path, 'wrong-checksum-value', 'library');

        $this->assertFalse($result->passed);
        $this->assertTrue($result->hasBlockingFailures());

        $checksumFailure = collect($result->failures)->firstWhere('check', 'checksum_match');
        $this->assertNotNull($checksumFailure);
        $this->assertSame(StageResult::SEVERITY_BLOCK, $checksumFailure['severity']);
        $this->assertStringContainsString('Checksum mismatch', $checksumFailure['message']);
    }

    public function test_skips_checksum_for_non_library(): void
    {
        $path = base_path('tests/Fixtures/Skills/valid-skill');

        // Upload source should skip checksum verification even with an expectedChecksum
        $result = $this->validator->validate($path, 'some-checksum', 'upload');

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->failures);
        $this->assertArrayHasKey('file_hashes', $result->details);
    }
}
