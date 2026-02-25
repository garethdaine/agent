<?php

namespace Tests\Feature\Messenger;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\MessengerEventDeduplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $connectorAccount;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->connectorAccount = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Workspace',
            'credentials' => ['signing_secret' => 'test'],
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [],
            'account_key' => 'T12345',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deduplication_prune_removes_old_records(): void
    {
        // Create old deduplication records (25 hours old)
        $oldTime = now()->subHours(25);
        MessengerEventDeduplication::insert([
            [
                'connector_account_id' => $this->connectorAccount->id,
                'event_id' => 'old_event_1',
                'expires_at' => $oldTime,
                'created_at' => $oldTime,
            ],
            [
                'connector_account_id' => $this->connectorAccount->id,
                'event_id' => 'old_event_2',
                'expires_at' => $oldTime,
                'created_at' => $oldTime,
            ],
        ]);

        $this->assertDatabaseCount('messenger_event_deduplication', 2);

        Artisan::call('messenger:prune', ['--deduplication' => true]);

        $this->assertDatabaseCount('messenger_event_deduplication', 0);
        $this->assertStringContainsString('Pruned 2 deduplication records', Artisan::output());
    }

    public function test_deduplication_prune_keeps_recent_records(): void
    {
        // Create a mix of old and recent records
        $oldTime = now()->subHours(25);
        $recentTime = now()->subHours(12);

        MessengerEventDeduplication::insert([
            [
                'connector_account_id' => $this->connectorAccount->id,
                'event_id' => 'old_event',
                'expires_at' => $oldTime,
                'created_at' => $oldTime,
            ],
            [
                'connector_account_id' => $this->connectorAccount->id,
                'event_id' => 'recent_event',
                'expires_at' => $recentTime,
                'created_at' => $recentTime,
            ],
        ]);

        Artisan::call('messenger:prune', ['--deduplication' => true]);

        $this->assertDatabaseCount('messenger_event_deduplication', 1);
        $this->assertDatabaseHas('messenger_event_deduplication', [
            'event_id' => 'recent_event',
        ]);
    }

    public function test_session_prune_respects_retention_period(): void
    {
        // Create an old archived session (35 days old)
        $oldSession = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'connector_account_id' => $this->connectorAccount->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C123',
            'status' => ChatSession::STATUS_ARCHIVED,
            'created_at' => now()->subDays(35),
            'updated_at' => now()->subDays(35),
        ]);

        // Create a recent archived session (15 days old)
        $recentSession = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'connector_account_id' => $this->connectorAccount->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C456',
            'status' => ChatSession::STATUS_ARCHIVED,
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(15),
        ]);

        // Create an active session (should not be pruned regardless of age)
        $activeSession = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'connector_account_id' => $this->connectorAccount->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C789',
            'status' => ChatSession::STATUS_ACTIVE,
            'created_at' => now()->subDays(35),
            'updated_at' => now()->subDays(35),
        ]);

        Artisan::call('messenger:prune', ['--sessions' => true]);

        $this->assertDatabaseMissing('chat_sessions', ['id' => $oldSession->id]);
        $this->assertDatabaseHas('chat_sessions', ['id' => $recentSession->id]);
        $this->assertDatabaseHas('chat_sessions', ['id' => $activeSession->id]);
    }

    public function test_message_prune_respects_retention_period(): void
    {
        config(['messenger.attachments.retention_days' => 90]);

        $session = ChatSession::factory()
            ->forUser($this->user)
            ->forConnector($this->connectorAccount)
            ->create();

        // Create old message (95 days old)
        ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->connectorAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Old message',
            'idempotency_key' => hash('sha256', 'old'),
            'created_at' => now()->subDays(95),
        ]);

        // Create recent message (30 days old)
        ChatMessage::create([
            'id' => Str::uuid()->toString(),
            'chat_session_id' => $session->id,
            'connector_account_id' => $this->connectorAccount->id,
            'direction' => ChatMessage::DIRECTION_INBOUND,
            'content' => 'Recent message',
            'idempotency_key' => hash('sha256', 'recent'),
            'created_at' => now()->subDays(30),
        ]);

        Artisan::call('messenger:prune', ['--messages' => true]);

        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseHas('chat_messages', ['content' => 'Recent message']);
    }

    public function test_attachment_prune_removes_expired_attachments(): void
    {
        Storage::fake('local');

        $session = ChatSession::factory()
            ->forUser($this->user)
            ->forConnector($this->connectorAccount)
            ->create();

        $message = ChatMessage::factory()
            ->forSession($session)
            ->create();

        // Create an expired attachment
        $storagePath = "messenger/{$this->user->id}/{$session->id}/test_file.pdf";
        Storage::disk('local')->put($storagePath, 'test content');

        ChatAttachment::create([
            'id' => Str::uuid()->toString(),
            'chat_message_id' => $message->id,
            'filename' => 'test_file.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'storage_path' => $storagePath,
            'scan_status' => ChatAttachment::SCAN_STATUS_SKIPPED,
            'expires_at' => now()->subDays(1),
            'created_at' => now()->subDays(31),
        ]);

        $this->assertDatabaseCount('chat_attachments', 1);

        Artisan::call('messenger:prune', ['--attachments' => true]);

        $this->assertDatabaseCount('chat_attachments', 0);
        Storage::disk('local')->assertMissing($storagePath);
    }

    public function test_dry_run_does_not_delete_records(): void
    {
        $oldTime = now()->subHours(25);
        MessengerEventDeduplication::create([
            'connector_account_id' => $this->connectorAccount->id,
            'event_id' => 'old_event',
            'expires_at' => $oldTime,
            'created_at' => $oldTime,
        ]);

        Artisan::call('messenger:prune', [
            '--deduplication' => true,
            '--dry-run' => true,
        ]);

        // Record should still exist
        $this->assertDatabaseCount('messenger_event_deduplication', 1);
        $this->assertStringContainsString('Would prune 1 deduplication records', Artisan::output());
    }

    public function test_schedule_registration(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();

        $hasHourlyDeduplication = false;
        $hasDailyCleanup = false;

        foreach ($events as $event) {
            if (str_contains($event->command ?? '', 'messenger:prune --deduplication')) {
                $hasHourlyDeduplication = true;
            }
            if (str_contains($event->command ?? '', 'messenger:prune --sessions --messages --attachments')) {
                $hasDailyCleanup = true;
            }
        }

        $this->assertTrue($hasHourlyDeduplication, 'Hourly deduplication prune should be scheduled');
        $this->assertTrue($hasDailyCleanup, 'Daily cleanup prune should be scheduled');
    }

    public function test_no_options_shows_warning(): void
    {
        $exitCode = Artisan::call('messenger:prune');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('No prune options specified', Artisan::output());
    }

    public function test_combined_options_work_together(): void
    {
        $oldTime = now()->subHours(25);
        MessengerEventDeduplication::create([
            'connector_account_id' => $this->connectorAccount->id,
            'event_id' => 'old_event',
            'expires_at' => $oldTime,
            'created_at' => $oldTime,
        ]);

        $oldSession = ChatSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'connector_account_id' => $this->connectorAccount->id,
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C123',
            'status' => ChatSession::STATUS_ARCHIVED,
            'created_at' => now()->subDays(35),
            'updated_at' => now()->subDays(35),
        ]);

        Artisan::call('messenger:prune', [
            '--deduplication' => true,
            '--sessions' => true,
        ]);

        $output = Artisan::output();

        $this->assertDatabaseCount('messenger_event_deduplication', 0);
        $this->assertDatabaseMissing('chat_sessions', ['id' => $oldSession->id]);
        $this->assertStringContainsString('Pruned 1 deduplication records', $output);
        $this->assertStringContainsString('Pruned 1 sessions', $output);
    }
}
