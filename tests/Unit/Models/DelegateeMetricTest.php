<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_profile(): void
    {
        $profile = DelegateeProfile::factory()->create();
        $metric = DelegateeMetric::factory()->create(['delegatee_profile_id' => $profile->id]);

        $this->assertInstanceOf(DelegateeProfile::class, $metric->profile);
        $this->assertEquals($profile->id, $metric->profile->id);
    }

    public function test_casts_window_json_fields(): void
    {
        $window24h = [
            'total_attempts' => 10,
            'successful_attempts' => 8,
            'success_rate' => 0.8,
            'avg_duration_ms' => 5000,
        ];
        $window7d = [
            'total_attempts' => 50,
            'successful_attempts' => 45,
            'success_rate' => 0.9,
            'avg_duration_ms' => 4500,
        ];

        $metric = DelegateeMetric::factory()->create([
            'window_24h_json' => $window24h,
            'window_7d_json' => $window7d,
        ]);

        $metric->refresh();

        $this->assertIsArray($metric->window_24h_json);
        $this->assertIsArray($metric->window_7d_json);
        $this->assertEquals(10, $metric->window_24h_json['total_attempts']);
        $this->assertEquals(50, $metric->window_7d_json['total_attempts']);
        $this->assertEquals(0.8, $metric->window_24h_json['success_rate']);
    }

    public function test_casts_last_recomputed_at(): void
    {
        $now = Carbon::now()->startOfSecond();
        $metric = DelegateeMetric::factory()->create([
            'last_recomputed_at' => $now,
        ]);

        $metric->refresh();

        $this->assertInstanceOf(Carbon::class, $metric->last_recomputed_at);
        $this->assertTrue(
            $now->diffInSeconds($metric->last_recomputed_at) < 2,
            'last_recomputed_at should be within 2 seconds of the saved value'
        );
    }

    public function test_last_recomputed_at_can_be_null(): void
    {
        $metric = DelegateeMetric::factory()->create([
            'last_recomputed_at' => null,
        ]);

        $metric->refresh();

        $this->assertNull($metric->last_recomputed_at);
    }

    public function test_window_json_fields_can_be_null(): void
    {
        $metric = DelegateeMetric::factory()->create([
            'window_24h_json' => null,
            'window_7d_json' => null,
        ]);

        $metric->refresh();

        $this->assertNull($metric->window_24h_json);
        $this->assertNull($metric->window_7d_json);
    }
}
