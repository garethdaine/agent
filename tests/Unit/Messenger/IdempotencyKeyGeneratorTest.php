<?php

namespace Tests\Unit\Messenger;

use App\DTOs\Messenger\NormalizedMessage;
use App\Models\ConnectorAccount;
use App\Support\Messenger\IdempotencyKeyGenerator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class IdempotencyKeyGeneratorTest extends TestCase
{
    private IdempotencyKeyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new IdempotencyKeyGenerator;
    }

    public function test_generates_key_using_provider_message_id_when_available(): void
    {
        $account = $this->createMockAccount('slack', 'account-123');

        $message = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerMessageId: '1234567890.123456',
        );

        $key1 = $this->generator->generate($message, $account);
        $key2 = $this->generator->generate($message, $account);

        // Keys should be consistent
        $this->assertEquals($key1, $key2);
        // Key should be a 64-character hex string (SHA256)
        $this->assertEquals(64, strlen($key1));
    }

    public function test_generates_fallback_key_without_message_id(): void
    {
        $account = $this->createMockAccount('telegram', 'account-456');

        $timestamp = Carbon::now();

        $message = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_1', 'attach_2'],
        );

        $key1 = $this->generator->generate($message, $account);
        $key2 = $this->generator->generate($message, $account);

        // Keys should be consistent
        $this->assertEquals($key1, $key2);
        // Key should be a 64-character hex string (SHA256)
        $this->assertEquals(64, strlen($key1));
    }

    public function test_different_content_produces_different_keys(): void
    {
        $account = $this->createMockAccount('slack', 'account-123');

        $timestamp = Carbon::now();

        $message1 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
        );

        $message2 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world!', // Different content
            providerTimestamp: $timestamp,
        );

        $key1 = $this->generator->generate($message1, $account);
        $key2 = $this->generator->generate($message2, $account);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_different_attachments_produce_different_keys(): void
    {
        $account = $this->createMockAccount('slack', 'account-123');

        $timestamp = Carbon::now();

        $message1 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_1'],
        );

        $message2 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_2'], // Different attachment
        );

        $key1 = $this->generator->generate($message1, $account);
        $key2 = $this->generator->generate($message2, $account);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_different_provider_message_ids_produce_different_keys(): void
    {
        $account = $this->createMockAccount('slack', 'account-123');

        $message1 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerMessageId: 'msg-1',
        );

        $message2 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerMessageId: 'msg-2',
        );

        $key1 = $this->generator->generate($message1, $account);
        $key2 = $this->generator->generate($message2, $account);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_same_content_different_accounts_produce_different_keys(): void
    {
        $account1 = $this->createMockAccount('slack', 'account-123');
        $account2 = $this->createMockAccount('slack', 'account-456');

        $message = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerMessageId: 'msg-1',
        );

        $key1 = $this->generator->generate($message, $account1);
        $key2 = $this->generator->generate($message, $account2);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_attachment_order_does_not_affect_key(): void
    {
        $account = $this->createMockAccount('slack', 'account-123');

        $timestamp = Carbon::now();

        $message1 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_1', 'attach_2', 'attach_3'],
        );

        $message2 = new NormalizedMessage(
            providerUserId: 'U12345',
            channelId: 'C12345',
            content: 'Hello world',
            providerTimestamp: $timestamp,
            attachmentIds: ['attach_3', 'attach_1', 'attach_2'], // Different order
        );

        $key1 = $this->generator->generate($message1, $account);
        $key2 = $this->generator->generate($message2, $account);

        // Keys should be the same because attachments are sorted before hashing
        $this->assertEquals($key1, $key2);
    }

    private function createMockAccount(string $provider, string $id): ConnectorAccount
    {
        $account = new ConnectorAccount;
        $account->forceFill([
            'id' => $id,
            'provider' => $provider,
        ]);

        return $account;
    }
}
