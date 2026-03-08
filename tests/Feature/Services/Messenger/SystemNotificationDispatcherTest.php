<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Jobs\Messenger\SendOutboundMessage;
use App\Models\AgentFeatureSetting;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Services\Messenger\SystemNotificationDispatcher;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SystemNotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ConnectorAccount $account;

    private SystemNotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->account = ConnectorAccount::factory()->discord()->connected()->create();
        $this->account->setSystemNotifications([
            'enabled' => true,
            'channel_id' => '123456789',
            'thread_id' => null,
            'notification_level' => 'lifecycle',
            'event_types' => [],
        ]);

        MessengerIdentityLink::factory()
            ->forUser($this->user)
            ->forConnector($this->account)
            ->create(['status' => MessengerIdentityLink::STATUS_APPROVED]);

        $this->enableFeatureFlag();

        $this->dispatcher = app(SystemNotificationDispatcher::class);
    }

    public function test_dispatches_notification_when_flag_enabled_and_account_configured(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Test Ritual Completed',
            body: 'The ritual completed successfully.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertDispatched(SendOutboundMessage::class, function (SendOutboundMessage $job) {
            return str_contains($job->content, 'Test Ritual Completed')
                && str_contains($job->content, 'ℹ️');
        });
    }

    public function test_skips_when_feature_flag_disabled(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->disableFeatureFlag();

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Should Not Send',
            body: 'This should not be sent.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_skips_accounts_with_system_notifications_disabled(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->setSystemNotifications([
            'enabled' => false,
            'channel_id' => '123456789',
            'notification_level' => 'lifecycle',
        ]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Should Not Send',
            body: 'Account notifications disabled.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_filters_by_notification_level_escalations_only(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->setSystemNotifications([
            'enabled' => true,
            'channel_id' => '123456789',
            'notification_level' => 'escalations_only',
            'event_types' => [],
        ]);

        // Info severity should be filtered out by escalations_only
        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Info Notification',
            body: 'This should be filtered.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_escalations_only_allows_error_severity(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->setSystemNotifications([
            'enabled' => true,
            'channel_id' => '123456789',
            'notification_level' => 'escalations_only',
            'event_types' => [],
        ]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.escalation_timed_out',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_ERROR,
            title: 'Escalation Timed Out',
            body: 'This should pass through.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertDispatched(SendOutboundMessage::class);
    }

    public function test_filters_by_event_types_whitelist(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->setSystemNotifications([
            'enabled' => true,
            'channel_id' => '123456789',
            'notification_level' => 'lifecycle',
            'event_types' => ['ritual.completed'],
        ]);

        // Event type not in whitelist
        $payload = new SystemNotificationPayload(
            type: 'agent.job_failed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_ERROR,
            title: 'Job Failed',
            body: 'Not in whitelist.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_allows_events_matching_whitelist(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->setSystemNotifications([
            'enabled' => true,
            'channel_id' => '123456789',
            'notification_level' => 'lifecycle',
            'event_types' => ['ritual.completed'],
        ]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'In Whitelist',
            body: 'This is allowed.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertDispatched(SendOutboundMessage::class);
    }

    public function test_handles_multiple_eligible_accounts(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        // Create a second account
        $account2 = ConnectorAccount::factory()->slack()->connected()->create();
        $account2->setSystemNotifications([
            'enabled' => true,
            'channel_id' => 'C987654321',
            'notification_level' => 'lifecycle',
            'event_types' => [],
        ]);

        MessengerIdentityLink::factory()
            ->forUser($this->user)
            ->forConnector($account2)
            ->create(['status' => MessengerIdentityLink::STATUS_APPROVED]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Multi-Account Test',
            body: 'Should dispatch to both.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertDispatched(SendOutboundMessage::class, 2);
    }

    public function test_never_throws_on_errors(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        // Use a non-existent user ID — should not throw
        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: 999999,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Ghost User',
            body: 'No accounts linked.',
        );

        // Should not throw
        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_skips_disconnected_accounts(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->update(['status' => ConnectorAccount::STATUS_DISCONNECTED]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'Disconnected Account',
            body: 'Should not send.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_skips_accounts_without_channel_id(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $this->account->setSystemNotifications([
            'enabled' => true,
            'channel_id' => null,
            'notification_level' => 'lifecycle',
        ]);

        $payload = new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_INFO,
            title: 'No Channel',
            body: 'Missing channel ID.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertNotDispatched(SendOutboundMessage::class);
    }

    public function test_should_notify_returns_correct_results(): void
    {
        $this->assertTrue($this->dispatcher->shouldNotify('lifecycle', 'info'));
        $this->assertTrue($this->dispatcher->shouldNotify('lifecycle', 'warning'));
        $this->assertTrue($this->dispatcher->shouldNotify('lifecycle', 'error'));

        $this->assertFalse($this->dispatcher->shouldNotify('escalations_only', 'info'));
        $this->assertFalse($this->dispatcher->shouldNotify('escalations_only', 'warning'));
        $this->assertTrue($this->dispatcher->shouldNotify('escalations_only', 'error'));

        $this->assertTrue($this->dispatcher->shouldNotify('verbose', 'info'));
        $this->assertTrue($this->dispatcher->shouldNotify('verbose', 'warning'));
        $this->assertTrue($this->dispatcher->shouldNotify('verbose', 'error'));
    }

    public function test_formats_message_with_severity_emoji(): void
    {
        Bus::fake([SendOutboundMessage::class]);

        $payload = new SystemNotificationPayload(
            type: 'agent.job_failed',
            userId: $this->user->id,
            severity: SystemNotificationPayload::SEVERITY_ERROR,
            title: 'Job Failed',
            body: 'Details here.',
        );

        $this->dispatcher->dispatch($payload);

        Bus::assertDispatched(SendOutboundMessage::class, function (SendOutboundMessage $job) {
            return str_contains($job->content, '🔴')
                && str_contains($job->content, '**Job Failed**')
                && str_contains($job->content, 'Details here.');
        });
    }

    private function enableFeatureFlag(): void
    {
        AgentFeatureSetting::query()->updateOrCreate(
            ['key' => FeatureFlagManager::MESSENGER_SYSTEM_NOTIFICATIONS_ENABLED],
            ['is_enabled' => true]
        );
    }

    private function disableFeatureFlag(): void
    {
        AgentFeatureSetting::query()->updateOrCreate(
            ['key' => FeatureFlagManager::MESSENGER_SYSTEM_NOTIFICATIONS_ENABLED],
            ['is_enabled' => false]
        );
    }
}
