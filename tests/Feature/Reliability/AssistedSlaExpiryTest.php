<?php

declare(strict_types=1);

namespace Tests\Feature\Reliability;

use App\Enums\ReliabilityRunClassification;
use App\Models\AgentJobRun;
use App\Models\RunClassification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AssistedSlaExpiryTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_command_reclassifies_expired_assisted_run_to_failed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:00:00+00:00'));

        $run = AgentJobRun::factory()->create();

        $classification = RunClassification::query()->create([
            'run_id' => $run->id,
            'workflow_key' => $run->job->workflow_key,
            'classification' => ReliabilityRunClassification::ASSISTED->value,
            'weight' => 0.7,
            'hard_fail' => false,
            'classification_reason_code' => 'assisted_pending_verification',
            'verification_completed_at' => null,
            'assisted_sla_expires_at' => now()->subMinute(),
            'classified_at' => now()->subDay(),
            'metadata_json' => ['source' => 'test'],
        ]);

        $exitCode = Artisan::call('agent:reliability-assisted-sla');

        $this->assertSame(0, $exitCode);

        $classification->refresh();

        $this->assertSame(ReliabilityRunClassification::FAILED->value, $classification->classification);
        $this->assertSame(0.0, (float) $classification->weight);
        $this->assertSame('assisted_sla_expired', $classification->classification_reason_code);
        $this->assertNotNull($classification->reclassified_at);
    }

    public function test_command_keeps_assisted_run_when_sla_has_not_expired(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T12:00:00+00:00'));

        $run = AgentJobRun::factory()->create();

        $classification = RunClassification::query()->create([
            'run_id' => $run->id,
            'workflow_key' => $run->job->workflow_key,
            'classification' => ReliabilityRunClassification::ASSISTED->value,
            'weight' => 0.7,
            'hard_fail' => false,
            'classification_reason_code' => 'assisted_pending_verification',
            'verification_completed_at' => null,
            'assisted_sla_expires_at' => now()->addHour(),
            'classified_at' => now()->subHour(),
            'metadata_json' => ['source' => 'test'],
        ]);

        $exitCode = Artisan::call('agent:reliability-assisted-sla');

        $this->assertSame(0, $exitCode);

        $classification->refresh();

        $this->assertSame(ReliabilityRunClassification::ASSISTED->value, $classification->classification);
        $this->assertSame(0.7, (float) $classification->weight);
        $this->assertNull($classification->reclassified_at);
    }
}
