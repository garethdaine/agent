<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Validation;

use App\Messenger\Validation\ChatActionPolicyValidator;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChatActionPolicyValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ChatActionPolicyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(ChatActionPolicyValidator::class);
    }

    #[Test]
    public function owner_can_access_their_job(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);

        $result = $this->validator->authorizeJobAccess($user, $job);

        $this->assertTrue($result->isAuthorized());
    }

    #[Test]
    public function non_owner_cannot_access_job(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $result = $this->validator->authorizeJobAccess($otherUser, $job);

        $this->assertFalse($result->isAuthorized());
    }

    #[Test]
    public function denial_message_is_generic(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);

        $result = $this->validator->authorizeJobAccess($otherUser, $job);

        $this->assertEquals('Permission denied', $result->getMessage());
    }

    #[Test]
    public function nonexistent_job_returns_same_denial_as_unauthorized(): void
    {
        $user = User::factory()->create();

        $result = $this->validator->authorizeJobById($user, 99999);

        $this->assertFalse($result->isAuthorized());
        $this->assertEquals('Permission denied', $result->getMessage());
    }

    #[Test]
    public function owner_can_access_their_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
        ]);

        $result = $this->validator->authorizeRunAccess($user, $run);

        $this->assertTrue($result->isAuthorized());
    }

    #[Test]
    public function non_owner_cannot_access_run(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $owner->id,
        ]);

        $result = $this->validator->authorizeRunAccess($otherUser, $run);

        $this->assertFalse($result->isAuthorized());
        $this->assertEquals('Permission denied', $result->getMessage());
    }

    #[Test]
    public function owner_can_steer_their_run(): void
    {
        $user = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
        ]);

        $result = $this->validator->authorizeSteerAccess($user, $run);

        $this->assertTrue($result->isAuthorized());
    }

    #[Test]
    public function non_owner_cannot_steer_run(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $owner->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $owner->id,
        ]);

        $result = $this->validator->authorizeSteerAccess($otherUser, $run);

        $this->assertFalse($result->isAuthorized());
    }
}
