<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureLicenseValid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RunnerModelsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureLicenseValid::class);
        config()->set('cache.default', 'array');
        Cache::flush();
    }

    public function test_codex_runner_models_fallback_uses_current_cli_aligned_defaults(): void
    {
        $user = User::factory()->create();

        config()->set('runtime.llm.openai.api_key', 'should-not-be-used-for-codex-picker');
        config()->set('agent.runner_models.codex', 'gpt-5.3-codex');

        $response = $this->actingAs($user)->getJson('/agent/api/v1/interrogation/runner-models?runner_type=codex');

        $response->assertOk()
            ->assertJsonPath('default', 'gpt-5.3-codex');

        $models = collect($response->json('data'));

        $this->assertTrue($models->contains(fn (array $model): bool => ($model['id'] ?? null) === 'gpt-5.3-codex'));
        $this->assertTrue($models->contains(fn (array $model): bool => ($model['id'] ?? null) === 'gpt-5.4'));
        $this->assertTrue($models->contains(fn (array $model): bool => ($model['id'] ?? null) === 'gpt-5.2-codex'));
        $this->assertTrue($models->contains(fn (array $model): bool => ($model['id'] ?? null) === 'gpt-5.1-codex-max'));
        $this->assertTrue($models->contains(fn (array $model): bool => ($model['id'] ?? null) === 'gpt-5.2'));
        $this->assertTrue($models->contains(fn (array $model): bool => ($model['id'] ?? null) === 'gpt-5.1-codex-mini'));
    }
}
