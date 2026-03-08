<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeWebPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_sessions_index_page_loads(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        RuntimeSession::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/messenger/runtime');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Messenger/Runtime/Index')
            ->has('sessions.data', 2)
        );
    }

    public function test_runtime_session_detail_page_loads(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        RuntimeTurn::factory()->count(3)->create(['runtime_session_id' => $session->id]);

        $response = $this->actingAs($user)->get("/messenger/runtime/{$session->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Messenger/Runtime/Show')
            ->has('session')
            ->has('session.turns', 3)
        );
    }

    public function test_runtime_sessions_route_is_registered(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        // Verify the route name exists and resolves to correct URL
        $this->assertEquals('/messenger/runtime', route('messenger.runtime.index', [], false));
        $this->assertEquals('/messenger/runtime/test-id', route('messenger.runtime.show', ['id' => 'test-id'], false));

        // Verify both routes are accessible when authenticated
        $response = $this->actingAs($user)->get(route('messenger.runtime.index'));
        $response->assertOk();
    }

    public function test_session_detail_shows_pending_approvals(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);
        $turn = RuntimeTurn::factory()->create(['runtime_session_id' => $session->id]);
        $toolCall = RuntimeToolCall::factory()->create([
            'runtime_turn_id' => $turn->id,
            'requires_approval' => true,
        ]);
        RuntimeApproval::factory()->create([
            'runtime_tool_call_id' => $toolCall->id,
            'requested_by' => $user->id,
            'state' => RuntimeApprovalState::Pending,
        ]);

        $response = $this->actingAs($user)->get("/messenger/runtime/{$session->id}");

        $response->assertInertia(fn ($page) => $page
            ->has('pendingApprovals', 1)
        );
    }

    public function test_runtime_sessions_index_requires_authentication(): void
    {
        $response = $this->get('/messenger/runtime');

        $response->assertRedirect('/login');
    }

    public function test_runtime_session_detail_returns_404_for_other_user(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $otherUser = User::factory()->create(['onboarding_completed_at' => now()]);
        $session = RuntimeSession::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/messenger/runtime/{$session->id}");

        $response->assertNotFound();
    }

    public function test_runtime_sessions_index_only_shows_user_sessions(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        $otherUser = User::factory()->create(['onboarding_completed_at' => now()]);

        RuntimeSession::factory()->count(2)->create(['user_id' => $user->id]);
        RuntimeSession::factory()->count(3)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/messenger/runtime');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('sessions.data', 2)
        );
    }
}
