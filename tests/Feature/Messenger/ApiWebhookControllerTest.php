<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $discordAccount;

    private string $discordPrivateKeyHex;

    private ConnectorAccount $whatsAppAccount;

    private string $whatsAppAppSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $discordKeypair = sodium_crypto_sign_keypair();
        $this->discordPrivateKeyHex = bin2hex(sodium_crypto_sign_secretkey($discordKeypair));
        $discordPublicKeyHex = bin2hex(sodium_crypto_sign_publickey($discordKeypair));

        $this->discordAccount = ConnectorAccount::query()->create([
            'provider' => ConnectorAccount::PROVIDER_DISCORD,
            'name' => 'Discord Test Account',
            'credentials' => [
                'application_id' => '1123456789012345678',
                'bot_token' => 'test-bot-token',
                'public_key' => $discordPublicKeyHex,
            ],
            'webhook_secret' => $discordPublicKeyHex,
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'replay_protection' => [
                    'strategy' => 'timestamp',
                    'window_seconds' => 300,
                ],
            ],
            'account_key' => 'discord-account-key',
        ]);

        $this->whatsAppAppSecret = 'test-whatsapp-app-secret';
        $this->whatsAppAccount = ConnectorAccount::query()->create([
            'provider' => ConnectorAccount::PROVIDER_WHATSAPP,
            'name' => 'WhatsApp Test Account',
            'credentials' => [
                'phone_number_id' => '123456789012345',
                'access_token' => 'test-whatsapp-access-token',
                'app_secret' => $this->whatsAppAppSecret,
                'verify_token' => 'verify-token',
            ],
            'webhook_secret' => 'verify-token',
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'replay_protection' => [
                    'strategy' => 'event_id',
                    'dedupe_ttl_seconds' => 3600,
                ],
            ],
            'account_key' => 'whatsapp-account-key',
        ]);
    }

    public function test_discord_ping_returns_pong_without_dispatching_jobs(): void
    {
        Bus::fake([ProcessInboundMessage::class]);

        $response = $this->callDiscordWebhook([
            'type' => 1,
        ]);

        $response->assertOk();
        $response->assertJson([
            'type' => 1,
        ]);
        Bus::assertNotDispatched(ProcessInboundMessage::class);
    }

    public function test_discord_command_interaction_dispatches_processing_job(): void
    {
        Bus::fake([ProcessInboundMessage::class]);

        $response = $this->callDiscordWebhook([
            'id' => 'interaction-123',
            'type' => 2,
            'channel_id' => 'channel-123',
            'member' => [
                'user' => [
                    'id' => 'user-123',
                    'bot' => false,
                ],
            ],
            'data' => [
                'name' => 'agent',
                'options' => [
                    ['name' => 'command', 'value' => 'status'],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'type' => 5,
        ]);
        Bus::assertDispatched(ProcessInboundMessage::class, function (ProcessInboundMessage $job): bool {
            return $job->connectorAccountId === $this->discordAccount->id
                && $job->provider === ConnectorAccount::PROVIDER_DISCORD
                && ($job->payload['id'] ?? null) === 'interaction-123';
        });
    }

    public function test_whatsapp_messages_dispatch_one_job_per_message(): void
    {
        Bus::fake([ProcessInboundMessage::class]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'entry-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '+14155550001',
                            'phone_number_id' => '123456789012345',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Jane Doe'],
                            'wa_id' => '14155550123',
                        ]],
                        'messages' => [
                            [
                                'id' => 'wamid-message-1',
                                'from' => '14155550123',
                                'timestamp' => '1700000000',
                                'type' => 'text',
                                'text' => ['body' => 'first'],
                            ],
                            [
                                'id' => 'wamid-message-2',
                                'from' => '14155550123',
                                'timestamp' => '1700000001',
                                'type' => 'text',
                                'text' => ['body' => 'second'],
                            ],
                        ],
                    ],
                ]],
            ]],
        ];

        $response = $this->callWhatsAppWebhook($payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        Bus::assertDispatchedTimes(ProcessInboundMessage::class, 2);
        Bus::assertDispatched(ProcessInboundMessage::class, function (ProcessInboundMessage $job): bool {
            $messages = data_get($job->payload, 'entry.0.changes.0.value.messages', []);

            return $job->connectorAccountId === $this->whatsAppAccount->id
                && $job->provider === ConnectorAccount::PROVIDER_WHATSAPP
                && is_array($messages)
                && count($messages) === 1;
        });
    }

    public function test_whatsapp_status_updates_do_not_dispatch_jobs(): void
    {
        Bus::fake([ProcessInboundMessage::class]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'entry-2',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '+14155550001',
                            'phone_number_id' => '123456789012345',
                        ],
                        'statuses' => [[
                            'id' => 'wamid-status-1',
                            'status' => 'delivered',
                            'timestamp' => '1700000002',
                            'recipient_id' => '14155550123',
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->callWhatsAppWebhook($payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        Bus::assertNotDispatched(ProcessInboundMessage::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callDiscordWebhook(array $payload): TestResponse
    {
        $timestamp = (string) time();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = bin2hex(
            sodium_crypto_sign_detached(
                $timestamp.$body,
                hex2bin($this->discordPrivateKeyHex)
            )
        );

        return $this->call(
            'POST',
            '/agent/api/v1/connectors/discord/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE_ED25519' => $signature,
                'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
            ],
            $body
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callWhatsAppWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->whatsAppAppSecret);

        return $this->call(
            'POST',
            '/agent/api/v1/connectors/whatsapp/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body
        );
    }
}
