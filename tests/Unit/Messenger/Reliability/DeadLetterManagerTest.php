<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Reliability;

use App\Jobs\Messenger\ProcessChatIntent;
use App\Messenger\Reliability\DeadLetterManager;
use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeadLetterManagerTest extends TestCase
{
    use RefreshDatabase;

    private DeadLetterManager $manager;

    private ConnectorAccount $connectorAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectorAccount = ConnectorAccount::factory()->slack()->create();
        $this->manager = new DeadLetterManager;
    }

    public function test_move_to_dead_letter_creates_record_with_payload_error_and_attempts(): void
    {
        $payload = [
            'message_id' => 'msg-123',
            'session_id' => 'session-456',
            'user_id' => 1,
            'content' => 'Test message content',
        ];
        $error = 'Connection timeout after 30 seconds';
        $attempts = 5;

        $deadLetter = $this->manager->moveToDeadLetter(
            $this->connectorAccount,
            $payload,
            $error,
            $attempts
        );

        $this->assertInstanceOf(MessengerDeadLetter::class, $deadLetter);
        $this->assertEquals($this->connectorAccount->id, $deadLetter->connector_account_id);
        $this->assertEquals($payload, $deadLetter->original_payload);
        $this->assertEquals($error, $deadLetter->error_message);
        $this->assertEquals($attempts, $deadLetter->attempts);
        $this->assertNotNull($deadLetter->failed_at);
        $this->assertNull($deadLetter->retried_at);

        // Verify error_history contains the initial error
        $this->assertIsArray($deadLetter->error_history);
        $this->assertCount(1, $deadLetter->error_history);
        $this->assertEquals($error, $deadLetter->error_history[0]['error']);
        $this->assertArrayHasKey('at', $deadLetter->error_history[0]);
    }

    public function test_move_to_dead_letter_persists_to_database(): void
    {
        $payload = ['test' => 'data'];
        $error = 'Test error';
        $attempts = 3;

        $deadLetter = $this->manager->moveToDeadLetter(
            $this->connectorAccount,
            $payload,
            $error,
            $attempts
        );

        $this->assertDatabaseHas('messenger_dead_letters', [
            'id' => $deadLetter->id,
            'connector_account_id' => $this->connectorAccount->id,
            'error_message' => $error,
            'attempts' => $attempts,
        ]);
    }

    public function test_retry_dispatches_job_and_deletes_on_success(): void
    {
        Queue::fake();

        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-123',
                'session_id' => 'session-456',
                'user_id' => 1,
            ],
            'error_message' => 'Original error',
            'error_history' => [['error' => 'Original error', 'at' => now()->toIso8601String()]],
            'attempts' => 3,
            'failed_at' => now(),
        ]);

        $result = $this->manager->retry($deadLetter->id);

        $this->assertTrue($result);
        Queue::assertPushed(ProcessChatIntent::class);
        $this->assertDatabaseMissing('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
    }

    public function test_retry_updates_error_history_on_failure(): void
    {
        // Create a dead letter with an original_payload that will fail to dispatch
        // by not providing required message/session/user IDs
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [], // Empty payload - will fail validation
            'error_message' => 'Original error',
            'error_history' => [['error' => 'Original error', 'at' => now()->toIso8601String()]],
            'attempts' => 3,
            'failed_at' => now()->subHour(),
        ]);

        // The retry should catch the exception and update the record
        $result = $this->manager->retry($deadLetter->id);

        // Refresh and check the dead letter was updated, not deleted
        $deadLetter->refresh();

        $this->assertFalse($result);
        $this->assertDatabaseHas('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
        $this->assertEquals(4, $deadLetter->attempts);
        $this->assertNotNull($deadLetter->retried_at);
        $this->assertCount(2, $deadLetter->error_history);
    }

    public function test_retry_bulk_processes_multiple_messages(): void
    {
        Queue::fake();

        $deadLetter1 = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-1',
                'session_id' => 'session-1',
                'user_id' => 1,
            ],
            'error_message' => 'Error 1',
            'error_history' => [['error' => 'Error 1', 'at' => now()->toIso8601String()]],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $deadLetter2 = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-2',
                'session_id' => 'session-2',
                'user_id' => 2,
            ],
            'error_message' => 'Error 2',
            'error_history' => [['error' => 'Error 2', 'at' => now()->toIso8601String()]],
            'attempts' => 2,
            'failed_at' => now(),
        ]);

        $results = $this->manager->retryBulk([$deadLetter1->id, $deadLetter2->id]);

        $this->assertCount(2, $results);

        $this->assertEquals($deadLetter1->id, $results[0]['id']);
        $this->assertTrue($results[0]['success']);

        $this->assertEquals($deadLetter2->id, $results[1]['id']);
        $this->assertTrue($results[1]['success']);

        Queue::assertPushed(ProcessChatIntent::class, 2);

        $this->assertDatabaseMissing('messenger_dead_letters', ['id' => $deadLetter1->id]);
        $this->assertDatabaseMissing('messenger_dead_letters', ['id' => $deadLetter2->id]);
    }

    public function test_retry_bulk_returns_mixed_results_when_some_fail(): void
    {
        Queue::fake();

        $deadLetterSuccess = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-1',
                'session_id' => 'session-1',
                'user_id' => 1,
            ],
            'error_message' => 'Error',
            'error_history' => [['error' => 'Error', 'at' => now()->toIso8601String()]],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $deadLetterFail = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [], // Empty - will fail
            'error_message' => 'Error',
            'error_history' => [['error' => 'Error', 'at' => now()->toIso8601String()]],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $results = $this->manager->retryBulk([$deadLetterSuccess->id, $deadLetterFail->id]);

        $this->assertCount(2, $results);

        // First should succeed
        $this->assertTrue($results[0]['success']);
        $this->assertDatabaseMissing('messenger_dead_letters', ['id' => $deadLetterSuccess->id]);

        // Second should fail
        $this->assertFalse($results[1]['success']);
        $this->assertDatabaseHas('messenger_dead_letters', ['id' => $deadLetterFail->id]);
    }

    public function test_get_by_connector_returns_filtered_results(): void
    {
        $otherConnector = ConnectorAccount::factory()->telegram()->create();

        // Create dead letters for the primary connector
        MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'slack-1'],
            'error_message' => 'Error 1',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'slack-2'],
            'error_message' => 'Error 2',
            'error_history' => [],
            'attempts' => 2,
            'failed_at' => now(),
        ]);

        // Create dead letter for different connector
        MessengerDeadLetter::create([
            'connector_account_id' => $otherConnector->id,
            'original_payload' => ['test' => 'telegram-1'],
            'error_message' => 'Error 3',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $results = $this->manager->getByConnector($this->connectorAccount->id);

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(
            fn ($item) => $item->connector_account_id === $this->connectorAccount->id
        ));
    }

    public function test_get_by_connector_returns_empty_for_nonexistent_connector(): void
    {
        // Use a valid UUID format that doesn't exist in the database
        $nonexistentId = '00000000-0000-0000-0000-000000000000';
        $results = $this->manager->getByConnector($nonexistentId);

        $this->assertCount(0, $results);
    }

    public function test_retry_throws_model_not_found_for_invalid_id(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->manager->retry(99999);
    }

    public function test_delete_removes_dead_letter(): void
    {
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'data'],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $this->manager->delete($deadLetter->id);

        $this->assertDatabaseMissing('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
    }

    public function test_get_count_returns_total_dead_letters(): void
    {
        MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 1],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 2],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $count = $this->manager->getCount();

        $this->assertEquals(2, $count);
    }

    public function test_get_count_for_connector_returns_filtered_count(): void
    {
        $otherConnector = ConnectorAccount::factory()->telegram()->create();

        MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 1],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        MessengerDeadLetter::create([
            'connector_account_id' => $otherConnector->id,
            'original_payload' => ['test' => 2],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $count = $this->manager->getCountForConnector($this->connectorAccount->id);

        $this->assertEquals(1, $count);
    }
}
