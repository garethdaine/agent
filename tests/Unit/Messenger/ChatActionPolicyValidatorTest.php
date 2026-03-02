<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ChatAction;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Services\Messenger\ChatActionPolicyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for ownership-based policy validation in ChatActionPolicyValidator.
 *
 * These tests verify that:
 * - Users can only update/delete their own jobs
 * - Users can only stop/retry/steer runs of jobs they own
 * - Read-only actions (jobs.list, runs.list_active) are allowed for all authenticated users
 * - Proper error messages are returned for unauthorized access
 */
final class ChatActionPolicyValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ChatActionPolicyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(ChatActionPolicyValidator::class);
    }

    /**
     * Helper to create a ChatAction with proper message/session/account context.
     */
    private function createActionWithContext(
        User $user,
        string $actionType,
        array $parameters = []
    ): ChatAction {
        $account = ConnectorAccount::factory()->create([
            'config' => [], // No channel restrictions
        ]);

        $session = ChatSession::factory()->create([
            'user_id' => $user->id,
            'connector_account_id' => $account->id,
        ]);

        $message = ChatMessage::factory()->create([
            'chat_session_id' => $session->id,
            'connector_account_id' => $account->id,
        ]);

        return ChatAction::factory()->create([
            'chat_message_id' => $message->id,
            'action_type' => $actionType,
            'parameters' => $parameters,
        ]);
    }

    #[Test]
    public function it_denies_job_update_when_user_does_not_own_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $action = $this->createActionWithContext($otherUser, 'jobs.update', [
            'job_id' => $job->id,
        ]);

        $result = $this->validator->validate($action, $otherUser);

        $this->assertFalse($result->allowed);
        $this->assertEquals('You do not own this resource', $result->reason);
    }

    #[Test]
    public function it_allows_job_update_when_user_owns_job(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $action = $this->createActionWithContext($user, 'jobs.update', [
            'job_id' => $job->id,
        ]);

        $result = $this->validator->validate($action, $user);

        $this->assertTrue($result->allowed);
    }

    #[Test]
    public function it_denies_run_stop_when_user_does_not_own_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $owner->id,
        ]);

        $action = $this->createActionWithContext($otherUser, 'runs.stop', [
            'run_id' => $run->id,
        ]);

        $result = $this->validator->validate($action, $otherUser);

        $this->assertFalse($result->allowed);
        $this->assertEquals('You do not own this resource', $result->reason);
    }

    #[Test]
    public function it_allows_jobs_list_for_any_authenticated_user(): void
    {
        $user = User::factory()->create();

        $action = $this->createActionWithContext($user, 'jobs.list', []);

        $result = $this->validator->validate($action, $user);

        $this->assertTrue($result->allowed);
    }

    #[Test]
    public function it_denies_job_delete_when_user_does_not_own_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $action = $this->createActionWithContext($otherUser, 'jobs.delete', [
            'job_id' => $job->id,
        ]);

        $result = $this->validator->validate($action, $otherUser);

        $this->assertFalse($result->allowed);
        $this->assertEquals('You do not own this resource', $result->reason);
    }

    #[Test]
    public function it_allows_job_delete_when_user_owns_job(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $action = $this->createActionWithContext($user, 'jobs.delete', [
            'job_id' => $job->id,
        ]);

        $result = $this->validator->validate($action, $user);

        $this->assertTrue($result->allowed);
    }

    #[Test]
    public function it_denies_run_retry_when_user_does_not_own_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $owner->id,
        ]);

        $action = $this->createActionWithContext($otherUser, 'runs.retry', [
            'run_id' => $run->id,
        ]);

        $result = $this->validator->validate($action, $otherUser);

        $this->assertFalse($result->allowed);
        $this->assertEquals('You do not own this resource', $result->reason);
    }

    #[Test]
    public function it_denies_run_steer_when_user_does_not_own_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $owner->id,
        ]);

        $action = $this->createActionWithContext($otherUser, 'runs.steer', [
            'run_id' => $run->id,
            'guidance' => 'some guidance',
        ]);

        $result = $this->validator->validate($action, $otherUser);

        $this->assertFalse($result->allowed);
        $this->assertEquals('You do not own this resource', $result->reason);
    }

    #[Test]
    public function it_denies_run_run_now_when_user_does_not_own_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $action = $this->createActionWithContext($otherUser, 'runs.run_now', [
            'job_id' => $job->id,
        ]);

        $result = $this->validator->validate($action, $otherUser);

        $this->assertFalse($result->allowed);
        $this->assertEquals('You do not own this resource', $result->reason);
    }

    #[Test]
    public function it_allows_run_stop_when_user_owns_job(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
        ]);

        $action = $this->createActionWithContext($user, 'runs.stop', [
            'run_id' => $run->id,
        ]);

        $result = $this->validator->validate($action, $user);

        $this->assertTrue($result->allowed);
    }

    #[Test]
    public function it_denies_with_resource_not_found_when_job_does_not_exist(): void
    {
        $user = User::factory()->create();

        $action = $this->createActionWithContext($user, 'jobs.update', [
            'job_id' => 99999,
        ]);

        $result = $this->validator->validate($action, $user);

        $this->assertFalse($result->allowed);
        $this->assertEquals('Resource not found', $result->reason);
    }

    #[Test]
    public function it_denies_with_resource_not_found_when_run_does_not_exist(): void
    {
        $user = User::factory()->create();

        $action = $this->createActionWithContext($user, 'runs.stop', [
            'run_id' => 99999,
        ]);

        $result = $this->validator->validate($action, $user);

        $this->assertFalse($result->allowed);
        $this->assertEquals('Resource not found', $result->reason);
    }

    #[Test]
    public function it_allows_runs_list_active_for_any_authenticated_user(): void
    {
        $user = User::factory()->create();

        $action = $this->createActionWithContext($user, 'runs.list_active', []);

        $result = $this->validator->validate($action, $user);

        $this->assertTrue($result->allowed);
    }
}
