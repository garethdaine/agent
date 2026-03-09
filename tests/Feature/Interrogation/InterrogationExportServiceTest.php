<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationSession;
use App\Models\User;
use App\Services\Interrogation\InterrogationExportService;
use App\Support\Interrogation\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class InterrogationExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->user = User::factory()->create();
    }

    public function test_export_summary_delegates_to_export_service(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->summarizing()
            ->create(['summary_json' => ['overview' => 'Test']]);

        $mockExportService = Mockery::mock(ExportService::class);
        $mockExportService->shouldReceive('exportSummary')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $session->id))
            ->andReturn('/tmp/exports/summary-123.md');

        $service = new InterrogationExportService($mockExportService);
        $path = $service->exportSummary($session);

        $this->assertSame('/tmp/exports/summary-123.md', $path);
    }

    public function test_export_plan_delegates_to_export_service(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->planning()
            ->create(['plan_json' => ['phases' => [['title' => 'Phase 1']]]]);

        $mockExportService = Mockery::mock(ExportService::class);
        $mockExportService->shouldReceive('exportPlan')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $session->id))
            ->andReturn('/tmp/exports/plan-456.md');

        $service = new InterrogationExportService($mockExportService);
        $path = $service->exportPlan($session);

        $this->assertSame('/tmp/exports/plan-456.md', $path);
    }
}
