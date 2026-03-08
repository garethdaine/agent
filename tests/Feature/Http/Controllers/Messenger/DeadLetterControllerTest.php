<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Messenger;

use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeadLetterControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $connectorAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->connectorAccount = ConnectorAccount::factory()->slack()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('messenger.dead-letters.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_returns_paginated_list(): void
    {
        // Create 30 dead letters to test pagination
        for ($i = 0; $i < 30; $i++) {
            MessengerDeadLetter::create([
                'connector_account_id' => $this->connectorAccount->id,
                'original_payload' => ['test' => $i],
                'error_message' => "Error {$i}",
                'error_history' => [['error' => "Error {$i}", 'at' => now()->toIso8601String()]],
                'attempts' => 1,
                'failed_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get(route('messenger.dead-letters.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Messenger/DeadLetters/Index')
            ->has('deadLetters.data', 25) // Default pagination
            ->has('deadLetters.links')
            ->has('connectors')
        );
    }

    public function test_index_filters_by_connector(): void
    {
        $otherConnector = ConnectorAccount::factory()->telegram()->create();

        // Create dead letters for both connectors
        MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'slack'],
            'error_message' => 'Slack error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        MessengerDeadLetter::create([
            'connector_account_id' => $otherConnector->id,
            'original_payload' => ['test' => 'telegram'],
            'error_message' => 'Telegram error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messenger.dead-letters.index', [
                'connector' => $this->connectorAccount->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Messenger/DeadLetters/Index')
            ->has('deadLetters.data', 1)
            ->where('deadLetters.data.0.connector_account_id', $this->connectorAccount->id)
        );
    }

    public function test_show_requires_authentication(): void
    {
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'data'],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->get(route('messenger.dead-letters.show', $deadLetter->id));

        $response->assertRedirect(route('login'));
    }

    public function test_show_returns_full_details_with_error_history(): void
    {
        $errorHistory = [
            ['error' => 'First error', 'at' => now()->subHours(2)->toIso8601String()],
            ['error' => 'Second error', 'at' => now()->subHour()->toIso8601String()],
            ['error' => 'Third error', 'at' => now()->toIso8601String()],
        ];

        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-123',
                'session_id' => 'session-456',
                'content' => 'Original message content',
            ],
            'error_message' => 'Third error',
            'error_history' => $errorHistory,
            'attempts' => 3,
            'failed_at' => now()->subHours(2),
            'retried_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messenger.dead-letters.show', $deadLetter->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Messenger/DeadLetters/Show')
            ->has('deadLetter')
            ->where('deadLetter.id', $deadLetter->id)
            ->where('deadLetter.error_message', 'Third error')
            ->has('deadLetter.error_history', 3)
            ->has('deadLetter.original_payload')
            ->has('deadLetter.connector_account')
        );
    }

    public function test_show_returns_404_for_nonexistent_dead_letter(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('messenger.dead-letters.show', 99999));

        $response->assertNotFound();
    }

    public function test_retry_single_message_and_redirects_with_success_flash(): void
    {
        Queue::fake();

        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-123',
                'session_id' => 'session-456',
                'user_id' => $this->user->id,
            ],
            'error_message' => 'Error',
            'error_history' => [['error' => 'Error', 'at' => now()->toIso8601String()]],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('messenger.dead-letters.retry', $deadLetter->id));

        $response->assertRedirect(route('messenger.dead-letters.index'));
        $response->assertSessionHas('success', 'Message retried successfully');

        $this->assertDatabaseMissing('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
    }

    public function test_retry_single_message_redirects_with_error_flash_on_failure(): void
    {
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [], // Empty - will fail
            'error_message' => 'Error',
            'error_history' => [['error' => 'Error', 'at' => now()->toIso8601String()]],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('messenger.dead-letters.retry', $deadLetter->id));

        $response->assertRedirect(route('messenger.dead-letters.index'));
        $response->assertSessionHas('error', 'Retry failed');

        $this->assertDatabaseHas('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
    }

    public function test_retry_requires_authentication(): void
    {
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'data'],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->post(route('messenger.dead-letters.retry', $deadLetter->id));

        $response->assertRedirect(route('login'));
    }

    public function test_retry_bulk_with_json_response(): void
    {
        Queue::fake();

        $deadLetter1 = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-1',
                'session_id' => 'session-1',
                'user_id' => $this->user->id,
            ],
            'error_message' => 'Error 1',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $deadLetter2 = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => [
                'message_id' => 'msg-2',
                'session_id' => 'session-2',
                'user_id' => $this->user->id,
            ],
            'error_message' => 'Error 2',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('messenger.dead-letters.retry-bulk'), [
                'ids' => [$deadLetter1->id, $deadLetter2->id],
            ]);

        $response->assertOk();
        $response->assertJson([
            ['id' => $deadLetter1->id, 'success' => true],
            ['id' => $deadLetter2->id, 'success' => true],
        ]);

        $this->assertDatabaseMissing('messenger_dead_letters', ['id' => $deadLetter1->id]);
        $this->assertDatabaseMissing('messenger_dead_letters', ['id' => $deadLetter2->id]);
    }

    public function test_retry_bulk_requires_authentication(): void
    {
        $response = $this->postJson(route('messenger.dead-letters.retry-bulk'), [
            'ids' => [1, 2],
        ]);

        $response->assertUnauthorized();
    }

    public function test_retry_bulk_validates_ids_parameter(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('messenger.dead-letters.retry-bulk'), []);

        $response->assertJsonValidationErrors(['ids']);
    }

    public function test_retry_bulk_handles_empty_ids_array(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('messenger.dead-letters.retry-bulk'), [
                'ids' => [],
            ]);

        $response->assertJsonValidationErrors(['ids']);
    }

    public function test_delete_removes_dead_letter_and_redirects(): void
    {
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'data'],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('messenger.dead-letters.destroy', $deadLetter->id));

        $response->assertRedirect(route('messenger.dead-letters.index'));
        $response->assertSessionHas('success', 'Dead letter deleted');

        $this->assertDatabaseMissing('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
    }

    public function test_delete_requires_authentication(): void
    {
        $deadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'data'],
            'error_message' => 'Error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->delete(route('messenger.dead-letters.destroy', $deadLetter->id));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('messenger_dead_letters', [
            'id' => $deadLetter->id,
        ]);
    }

    public function test_index_orders_by_failed_at_descending(): void
    {
        $oldDeadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'old'],
            'error_message' => 'Old error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now()->subDay(),
        ]);

        $newDeadLetter = MessengerDeadLetter::create([
            'connector_account_id' => $this->connectorAccount->id,
            'original_payload' => ['test' => 'new'],
            'error_message' => 'New error',
            'error_history' => [],
            'attempts' => 1,
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('messenger.dead-letters.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('deadLetters.data.0.id', $newDeadLetter->id)
            ->where('deadLetters.data.1.id', $oldDeadLetter->id)
        );
    }
}
