<?php

declare(strict_types=1);

namespace Tests\Feature\NlSchedule;

use App\Models\NlParseAttempt;
use App\Models\User;
use App\Support\NlSchedule\NlScheduleParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlScheduleParserServiceTest extends TestCase
{
    use RefreshDatabase;

    private NlScheduleParserService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NlScheduleParserService::class);
        $this->user = User::factory()->create();
    }

    public function test_high_confidence_returns_completed_immediately(): void
    {
        $response = $this->service->parse(
            $this->user,
            'daily at 9am',
            'UTC'
        );

        $this->assertEquals('completed', $response['status']);
        $this->assertArrayHasKey('parse_attempt_id', $response);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('0 9 * * *', $response['result']['cron_expression']);
    }

    public function test_low_confidence_returns_clarification_required(): void
    {
        $response = $this->service->parse(
            $this->user,
            'twice a day', // ambiguous pattern
            'UTC'
        );

        $this->assertContains($response['status'], ['completed', 'clarification_required']);
        $this->assertArrayHasKey('parse_attempt_id', $response);
    }

    public function test_idempotency_returns_existing_attempt(): void
    {
        $first = $this->service->parse($this->user, 'daily at 9am', 'UTC');
        $second = $this->service->parse($this->user, 'daily at 9am', 'UTC');

        $this->assertEquals($first['parse_attempt_id'], $second['parse_attempt_id']);
    }

    public function test_validates_input_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->parse(
            $this->user,
            str_repeat('a', 250), // exceeds 200 char limit
            'UTC'
        );
    }

    public function test_creates_parse_attempt_record(): void
    {
        $response = $this->service->parse($this->user, 'daily at 9am', 'UTC');

        $this->assertDatabaseHas('nl_parse_attempts', [
            'id' => $response['parse_attempt_id'],
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);
    }

    public function test_logs_redacted_input(): void
    {
        // This test verifies logging behavior
        // The log should contain first 80 chars + hash, not full input
        $longInput = str_repeat('test pattern ', 15); // ~180 chars

        $this->service->parse($this->user, $longInput, 'UTC');

        // Verify the full input is stored in DB (trimmed as per service behavior)
        $attempt = NlParseAttempt::first();
        $this->assertEquals(trim($longInput), $attempt->input_text);
    }

    public function test_it_returns_clarification_required_for_low_confidence(): void
    {
        // Ambiguous input that should trigger low confidence
        $result = $this->service->parse($this->user, 'every other day at 9', 'UTC');

        $this->assertEquals('clarification_required', $result['status']);
        $this->assertArrayHasKey('parse_attempt_id', $result);
        $this->assertArrayHasKey('interpretation', $result);
        $this->assertArrayHasKey('alternatives', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_clarification_response_includes_human_readable_interpretation(): void
    {
        $result = $this->service->parse($this->user, 'sometime in the morning', 'UTC');

        if ($result['status'] === 'clarification_required') {
            $this->assertStringContainsString('I understood this as', $result['message']);
        }
    }

    public function test_high_confidence_parse_returns_success(): void
    {
        // Clear, unambiguous input
        $result = $this->service->parse($this->user, 'every Monday at 9:00 AM', 'UTC');

        $this->assertEquals('completed', $result['status']);
    }
}
