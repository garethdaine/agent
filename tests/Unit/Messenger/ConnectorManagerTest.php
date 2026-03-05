<?php

namespace Tests\Unit\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\NormalizedMessage;
use App\DTOs\Messenger\OutboundPayload;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\ReplayProtectionStrategy;
use App\DTOs\Messenger\StreamingConfig;
use App\DTOs\Messenger\ThreadingStrategy;
use App\Models\ChatSession;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class ConnectorManagerTest extends TestCase
{
    public function test_it_resolves_registered_adapter(): void
    {
        $manager = new ConnectorManager;
        $manager->register('test', StubConnectorAdapter::class);

        $adapter = $manager->resolve('test');

        $this->assertInstanceOf(StubConnectorAdapter::class, $adapter);
    }

    public function test_it_throws_exception_for_unsupported_provider(): void
    {
        $manager = new ConnectorManager;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported messenger provider [unknown].');

        $manager->resolve('unknown');
    }

    public function test_it_returns_list_of_supported_providers(): void
    {
        $manager = new ConnectorManager;
        $manager->register('slack', StubConnectorAdapter::class);
        $manager->register('telegram', StubConnectorAdapter::class);

        $providers = $manager->getSupportedProviders();

        $this->assertSame(['slack', 'telegram'], $providers);
    }

    public function test_it_checks_if_provider_is_supported(): void
    {
        $manager = new ConnectorManager;
        $manager->register('slack', StubConnectorAdapter::class);

        $this->assertTrue($manager->isSupported('slack'));
        $this->assertTrue($manager->isSupported('SLACK')); // case insensitive
        $this->assertFalse($manager->isSupported('discord'));
    }

    public function test_provider_name_is_normalized(): void
    {
        $manager = new ConnectorManager;
        $manager->register('slack', StubConnectorAdapter::class);

        // Resolve with different casing and whitespace
        $adapter = $manager->resolve('  SLACK  ');

        $this->assertInstanceOf(StubConnectorAdapter::class, $adapter);
    }

    public function test_service_provider_binds_connector_manager_as_singleton(): void
    {
        $manager1 = app(ConnectorManager::class);
        $manager2 = app(ConnectorManager::class);

        $this->assertSame($manager1, $manager2);
    }
}

class StubConnectorAdapter implements ConnectorAdapterInterface
{
    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function parseInboundMessage(Request $request): NormalizedMessage
    {
        return new NormalizedMessage(
            providerUserId: 'user-123',
            channelId: 'channel-456',
            content: 'test message',
        );
    }

    public function sendMessage(ChatSession $session, OutboundPayload $payload): ProviderResponse
    {
        return ProviderResponse::success('msg-789');
    }

    public function editMessage(ChatSession $session, string $providerMessageId, string $content): ProviderResponse
    {
        return ProviderResponse::success($providerMessageId);
    }

    public function supportsMessageEditing(): bool
    {
        return false;
    }

    public function supportsThreading(): bool
    {
        return true;
    }

    public function getThreadingStrategy(): ThreadingStrategy
    {
        return ThreadingStrategy::Native;
    }

    public function getReplayProtectionStrategy(): ReplayProtectionStrategy
    {
        return ReplayProtectionStrategy::Timestamp;
    }

    public function supportsReactions(): bool
    {
        return false;
    }

    public function addReaction(ChatSession $session, string $messageId, string $emoji): ProviderResponse
    {
        return ProviderResponse::success($messageId);
    }

    public function getStreamingConfig(): StreamingConfig
    {
        return StreamingConfig::fallback();
    }
}
