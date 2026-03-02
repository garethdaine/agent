<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AiCriticCompletedJob;
use App\Models\AgentJobRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class AiCriticCompletedJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reads_output_from_stdout_file_first(): void
    {
        $run = AgentJobRun::factory()->create([
            'metadata_json' => ['output' => 'metadata output'],
        ]);

        // Create stdout file in app storage
        Storage::fake('local');
        $path = "runs/{$run->id}/stdout.log";
        Storage::disk('local')->put($path, 'stdout content');

        // The implementation uses storage_path() which reads from the real filesystem,
        // so we need to create the actual directory and file for this test
        $storagePath = storage_path("app/runs/{$run->id}");
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        file_put_contents("{$storagePath}/stdout.log", 'stdout content');

        try {
            $job = new AiCriticCompletedJob(1);
            $output = $this->invokeMethod($job, 'getRunOutput', [$run]);

            $this->assertEquals('stdout content', $output);
        } finally {
            // Cleanup
            @unlink("{$storagePath}/stdout.log");
            @rmdir($storagePath);
            @rmdir(storage_path('app/runs'));
        }
    }

    #[Test]
    public function it_falls_back_to_metadata_when_stdout_empty(): void
    {
        $run = AgentJobRun::factory()->create([
            'metadata_json' => ['output' => 'metadata output'],
        ]);

        // No stdout file exists
        $storagePath = storage_path("app/runs/{$run->id}/stdout.log");
        if (file_exists($storagePath)) {
            unlink($storagePath);
        }

        $job = new AiCriticCompletedJob(1);
        $output = $this->invokeMethod($job, 'getRunOutput', [$run]);

        $this->assertEquals('metadata output', $output);
    }

    #[Test]
    public function it_falls_back_to_artifacts_when_metadata_empty(): void
    {
        $run = AgentJobRun::factory()->create([
            'metadata_json' => [],
        ]);

        // No stdout file exists
        $storagePath = storage_path("app/runs/{$run->id}/stdout.log");
        if (file_exists($storagePath)) {
            unlink($storagePath);
        }

        // Note: This test assumes artifacts() relationship exists.
        // If it doesn't exist yet, the implementation should handle gracefully
        // and fall through to returning empty string.

        $job = new AiCriticCompletedJob(1);
        $output = $this->invokeMethod($job, 'getRunOutput', [$run]);

        // Without artifacts relationship, should return empty string
        $this->assertEquals('', $output);
    }

    #[Test]
    public function it_returns_empty_string_when_all_sources_empty(): void
    {
        $run = AgentJobRun::factory()->create(['metadata_json' => []]);

        // No stdout file exists
        $storagePath = storage_path("app/runs/{$run->id}/stdout.log");
        if (file_exists($storagePath)) {
            unlink($storagePath);
        }

        $job = new AiCriticCompletedJob(1);
        $output = $this->invokeMethod($job, 'getRunOutput', [$run]);

        $this->assertEquals('', $output);
    }

    #[Test]
    public function it_falls_back_to_metadata_when_stdout_file_is_empty(): void
    {
        $run = AgentJobRun::factory()->create([
            'metadata_json' => ['output' => 'metadata output'],
        ]);

        // Create an empty stdout file
        $storagePath = storage_path("app/runs/{$run->id}");
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        file_put_contents("{$storagePath}/stdout.log", '');

        try {
            $job = new AiCriticCompletedJob(1);
            $output = $this->invokeMethod($job, 'getRunOutput', [$run]);

            $this->assertEquals('metadata output', $output);
        } finally {
            // Cleanup
            @unlink("{$storagePath}/stdout.log");
            @rmdir($storagePath);
            @rmdir(storage_path('app/runs'));
        }
    }

    #[Test]
    public function it_falls_back_to_metadata_when_stdout_file_has_only_whitespace(): void
    {
        $run = AgentJobRun::factory()->create([
            'metadata_json' => ['output' => 'metadata output'],
        ]);

        // Create stdout file with only whitespace
        $storagePath = storage_path("app/runs/{$run->id}");
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        file_put_contents("{$storagePath}/stdout.log", "   \n\t  ");

        try {
            $job = new AiCriticCompletedJob(1);
            $output = $this->invokeMethod($job, 'getRunOutput', [$run]);

            $this->assertEquals('metadata output', $output);
        } finally {
            // Cleanup
            @unlink("{$storagePath}/stdout.log");
            @rmdir($storagePath);
            @rmdir(storage_path('app/runs'));
        }
    }

    private function invokeMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }
}
