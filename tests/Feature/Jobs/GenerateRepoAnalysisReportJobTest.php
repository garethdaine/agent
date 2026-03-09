<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\RepoAnalysis\GenerateRepoAnalysisReportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateRepoAnalysisReportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(GenerateRepoAnalysisReportJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(GenerateRepoAnalysisReportJob::class) ?: [])
        );
    }

    public function test_can_be_dispatched(): void
    {
        Queue::fake();

        GenerateRepoAnalysisReportJob::dispatch(1);

        Queue::assertPushed(GenerateRepoAnalysisReportJob::class);
    }
}
