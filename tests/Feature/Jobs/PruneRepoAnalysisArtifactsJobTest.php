<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RepoAnalysis\PruneRepoAnalysisArtifactsJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PruneRepoAnalysisArtifactsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(PruneRepoAnalysisArtifactsJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(PruneRepoAnalysisArtifactsJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        PruneRepoAnalysisArtifactsJob::dispatch();

        Queue::assertPushed(PruneRepoAnalysisArtifactsJob::class);
    }

    public function test_can_be_dispatched_with_dry_run(): void
    {
        Queue::fake();

        PruneRepoAnalysisArtifactsJob::dispatch(true);

        Queue::assertPushed(PruneRepoAnalysisArtifactsJob::class);
    }
}
