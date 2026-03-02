<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Documentation\DocsTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TooltipFragmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_fragment_api_resolves_fragment_from_registry_with_learn_more_slug(): void
    {
        $user = User::factory()->create();

        $entry = DocumentationEntry::query()->create([
            'domain' => 'product_doc',
            'slug' => 'jobs-timeouts',
            'locale' => 'en',
            'title' => 'Job Timeout Guidance',
            'summary' => 'How timeout handling works.',
            'section' => 'jobs',
            'audience' => 'internal',
            'status' => 'published',
            'body_markdown' => '# Timeout docs',
        ]);

        DocumentationFragment::query()->create([
            'ui_key' => 'jobs.timeout',
            'locale' => 'en',
            'short_text' => 'Jobs can timeout if workers stall.',
            'long_text' => 'Timeouts stop long-running executions to preserve scheduler health.',
            'severity' => 'warning',
            'status' => 'published',
            'learn_more_entry_id' => $entry->id,
            'feature_flag' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/fragments/jobs.timeout')
            ->assertOk()
            ->assertJsonPath('data.ui_key', 'jobs.timeout')
            ->assertJsonPath('data.short_text', 'Jobs can timeout if workers stall.')
            ->assertJsonPath('data.long_text', 'Timeouts stop long-running executions to preserve scheduler health.')
            ->assertJsonPath('data.severity', 'warning')
            ->assertJsonPath('data.learn_more_slug', 'jobs-timeouts');
    }

    public function test_missing_ui_key_emits_structured_telemetry_without_user_visible_error_details(): void
    {
        $user = User::factory()->create();

        $telemetry = Mockery::mock(DocsTelemetryService::class);
        $telemetry->shouldReceive('recordTooltipMiss')
            ->once()
            ->withArgs(function (string $uiKey, string $reason, array $context): bool {
                return $uiKey === 'unknown.ui.key'
                    && $reason === 'missing_key'
                    && ($context['source'] ?? null) === 'api';
            });

        $this->app->instance(DocsTelemetryService::class, $telemetry);

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/fragments/unknown.ui.key')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonMissingPath('data');
    }

    public function test_feature_flag_disabled_fragment_returns_not_found_and_emits_telemetry(): void
    {
        $user = User::factory()->create();

        config()->set('delegation.enabled', false);

        DocumentationFragment::query()->create([
            'ui_key' => 'delegation.warning',
            'locale' => 'en',
            'short_text' => 'Delegation can increase throughput.',
            'long_text' => null,
            'severity' => 'info',
            'status' => 'published',
            'feature_flag' => FeatureFlagManager::DELEGATION_ENABLED,
        ]);

        $telemetry = Mockery::mock(DocsTelemetryService::class);
        $telemetry->shouldReceive('recordTooltipMiss')
            ->once()
            ->withArgs(function (string $uiKey, string $reason, array $context): bool {
                return $uiKey === 'delegation.warning'
                    && $reason === 'feature_flag_disabled'
                    && ($context['feature_flag'] ?? null) === FeatureFlagManager::DELEGATION_ENABLED;
            });

        $this->app->instance(DocsTelemetryService::class, $telemetry);

        $this->actingAs($user)
            ->getJson('/agent/api/v1/docs/fragments/delegation.warning')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
