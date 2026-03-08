<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\DelegateeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeProfileSoulExposureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('delegation.enabled', true);
    }

    public function test_show_does_not_expose_soul_json(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->create([
            'user_id' => $user->id,
            'soul_json' => [
                'personality' => 'helpful assistant',
                'system_prompt' => 'You are a coding agent.',
                'user_context' => 'User prefers TypeScript.',
            ],
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/delegatee-profiles/{$profile->id}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertArrayNotHasKey('soul', $data);
        $this->assertArrayNotHasKey('soul_json', $data);
        $this->assertArrayNotHasKey('personality', $data);
        $this->assertArrayNotHasKey('system_prompt', $data);
        $this->assertArrayNotHasKey('user_context', $data);
    }

    public function test_index_does_not_expose_soul_json(): void
    {
        $user = User::factory()->create();
        DelegateeProfile::factory()->create([
            'user_id' => $user->id,
            'soul_json' => [
                'personality' => 'helpful assistant',
                'system_prompt' => 'You are a coding agent.',
                'user_context' => 'User prefers TypeScript.',
            ],
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/delegatee-profiles');

        $response->assertOk();
        $data = $response->json('data.0');

        $this->assertArrayNotHasKey('soul', $data);
        $this->assertArrayNotHasKey('soul_json', $data);
        $this->assertArrayNotHasKey('personality', $data);
        $this->assertArrayNotHasKey('system_prompt', $data);
        $this->assertArrayNotHasKey('user_context', $data);
    }
}
