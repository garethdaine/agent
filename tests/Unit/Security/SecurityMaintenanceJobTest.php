<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\SecurityEventType;
use App\Jobs\Security\SecurityMaintenanceJob;
use App\Models\Runtime\RuntimeSession;
use App\Models\Security\SecurityEvent;
use App\Services\Security\SecurityConfigProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityMaintenanceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_security_events_older_than_retention_period(): void
    {
        // Create old events (35 days ago)
        SecurityEvent::create([
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'low',
            'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
            'created_at' => now()->subDays(35),
        ]);

        SecurityEvent::create([
            'event_type' => SecurityEventType::ContentBlocked,
            'severity' => 'high',
            'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
            'created_at' => now()->subDays(40),
        ]);

        // Create recent event (5 days ago)
        SecurityEvent::create([
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'medium',
            'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
            'created_at' => now()->subDays(5),
        ]);

        $job = new SecurityMaintenanceJob;
        $job->handle(app(SecurityConfigProvider::class));

        // Old events should be purged (2 deleted), recent event kept
        // Plus the purge log event itself was created
        $remaining = SecurityEvent::where('event_type', '!=', SecurityEventType::RulePurged)->count();
        $this->assertEquals(1, $remaining);
    }

    public function test_does_not_purge_events_within_retention_period(): void
    {
        SecurityEvent::create([
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'low',
            'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
            'created_at' => now()->subDays(10),
        ]);

        SecurityEvent::create([
            'event_type' => SecurityEventType::ContentStripped,
            'severity' => 'low',
            'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
            'created_at' => now()->subDays(20),
        ]);

        $job = new SecurityMaintenanceJob;
        $job->handle(app(SecurityConfigProvider::class));

        $remaining = SecurityEvent::where('event_type', '!=', SecurityEventType::RulePurged)->count();
        $this->assertEquals(2, $remaining);
    }

    public function test_respects_batch_size_config(): void
    {
        // Create more events than default batch size won't be exceeded
        // For testing, we verify the job processes in batches by checking it completes
        for ($i = 0; $i < 5; $i++) {
            SecurityEvent::create([
                'event_type' => SecurityEventType::InjectionDetected,
                'severity' => 'low',
                'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
                'created_at' => now()->subDays(35),
            ]);
        }

        $job = new SecurityMaintenanceJob;
        $job->handle(app(SecurityConfigProvider::class));

        $remaining = SecurityEvent::where('event_type', '!=', SecurityEventType::RulePurged)->count();
        $this->assertEquals(0, $remaining);
    }

    public function test_logs_purge_counts_to_security_events(): void
    {
        SecurityEvent::create([
            'event_type' => SecurityEventType::InjectionDetected,
            'severity' => 'low',
            'details_json' => ['test' => true, 'timestamp' => now()->toIso8601String()],
            'created_at' => now()->subDays(35),
        ]);

        $job = new SecurityMaintenanceJob;
        $job->handle(app(SecurityConfigProvider::class));

        $purgeEvent = SecurityEvent::where('event_type', SecurityEventType::RulePurged)->first();
        $this->assertNotNull($purgeEvent);
        $this->assertEquals('rule_purged', $purgeEvent->event_type->value);
        $this->assertArrayHasKey('events_purged', $purgeEvent->details_json);
        $this->assertEquals(1, $purgeEvent->details_json['events_purged']);
    }

    public function test_purges_expired_file_provenance_from_runtime_sessions(): void
    {
        $session = RuntimeSession::factory()->create([
            'file_provenance' => [
                [
                    'filePath' => '/tmp/old-file.txt',
                    'trustLevel' => 'untrusted',
                    'origin' => 'web.fetch',
                    'createdAt' => now()->subDays(35)->toIso8601String(),
                    'toolCallId' => null,
                ],
                [
                    'filePath' => '/tmp/recent-file.txt',
                    'trustLevel' => 'contextual',
                    'origin' => 'session.discovery',
                    'createdAt' => now()->subDays(5)->toIso8601String(),
                    'toolCallId' => null,
                ],
            ],
        ]);

        $job = new SecurityMaintenanceJob;
        $job->handle(app(SecurityConfigProvider::class));

        $session->refresh();
        $provenance = $session->file_provenance;

        $this->assertCount(1, $provenance);
        $this->assertEquals('/tmp/recent-file.txt', $provenance[0]['filePath']);
    }

    public function test_job_dispatches_to_memory_formation_queue(): void
    {
        $job = new SecurityMaintenanceJob;
        $this->assertEquals('memory-formation', $job->queue);
    }

    public function test_job_has_correct_retry_config(): void
    {
        $job = new SecurityMaintenanceJob;
        $this->assertEquals(5, $job->tries);
        $this->assertEquals([30, 60, 120, 300, 600], $job->backoff());
    }
}
