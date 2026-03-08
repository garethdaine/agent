<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->withPersonalTeam()->create();
        $this->regularUser = User::factory()->withPersonalTeam()->create();

        config()->set('agent.roles.admin_user_ids', [$this->admin->id]);
    }

    public function test_index_returns_service_list(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/agent/api/v1/services');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['key', 'label', 'description', 'status', 'critical', 'pid'],
                ],
            ]);

        $data = $response->json('data');

        $keys = array_column($data, 'key');
        $this->assertContains('horizon', $keys);
        $this->assertContains('scheduler', $keys);
        $this->assertContains('reverb', $keys);
        $this->assertContains('messenger-gateway', $keys);
    }

    public function test_index_uses_user_friendly_labels(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/agent/api/v1/services');

        $data = $response->json('data');
        $labels = array_column($data, 'label');

        // Labels should be non-technical
        $this->assertContains('Task Queue', $labels);
        $this->assertContains('Scheduler', $labels);
        $this->assertContains('Live Updates', $labels);
        $this->assertContains('Messenger Gateway', $labels);

        // Labels should NOT contain technical terms like "Horizon" or "Reverb"
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('Horizon', $label);
            $this->assertStringNotContainsString('Reverb', $label);
            $this->assertStringNotContainsString('artisan', $label);
        }
    }

    public function test_index_accessible_by_non_admin(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/agent/api/v1/services');

        $response->assertOk();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/agent/api/v1/services');

        $response->assertUnauthorized();
    }

    public function test_restart_forbidden_for_non_admin(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/agent/api/v1/services/restart', [
                'service' => 'horizon',
            ]);

        $response->assertForbidden();
    }

    public function test_restart_requires_service_parameter(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/agent/api/v1/services/restart', []);

        $response->assertUnprocessable();
    }

    public function test_restart_rejects_unknown_service(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/agent/api/v1/services/restart', [
                'service' => 'nonexistent-service',
            ]);

        $response->assertUnprocessable();
        $this->assertFalse($response->json('data.success'));
    }

    public function test_restart_requires_authentication(): void
    {
        $response = $this->postJson('/agent/api/v1/services/restart', [
            'service' => 'horizon',
        ]);

        $response->assertUnauthorized();
    }

    public function test_service_status_includes_running_or_stopped(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/agent/api/v1/services');

        $data = $response->json('data');

        foreach ($data as $service) {
            $this->assertContains($service['status'], ['running', 'stopped']);
        }
    }

    public function test_service_descriptions_are_non_technical(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/agent/api/v1/services');

        $data = $response->json('data');

        foreach ($data as $service) {
            $this->assertNotEmpty($service['description']);
            // Descriptions should not reference internal commands
            $this->assertStringNotContainsString('php artisan', $service['description']);
            $this->assertStringNotContainsString('queue:work', $service['description']);
        }
    }
}
