<?php

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Http\Controllers\Controller;
use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ConnectorAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private const DISCORD_TYPE_PING = 1;

    private const DISCORD_TYPE_APPLICATION_COMMAND = 2;

    private const DISCORD_TYPE_MESSAGE_COMPONENT = 3;

    private const DISCORD_TYPE_APPLICATION_COMMAND_AUTOCOMPLETE = 4;

    private const DISCORD_TYPE_MODAL_SUBMIT = 5;

    private const DISCORD_RESPONSE_PONG = 1;

    private const DISCORD_RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE = 5;

    private const DISCORD_RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT = 8;

    public function handleSlack(Request $request): JsonResponse
    {
        // Handle Slack URL verification challenge
        if ($request->input('type') === 'url_verification') {
            return response()->json([
                'challenge' => $request->input('challenge'),
            ]);
        }

        /** @var ConnectorAccount $account */
        $account = $request->attributes->get('connector_account');

        Log::channel('messenger')->info('Slack webhook received', [
            'account_id' => $account->id,
            'event_type' => $request->input('event.type'),
            'event_id' => $request->input('event_id'),
        ]);

        // Skip bot messages to prevent loops
        if ($this->isBotMessage($request, 'slack')) {
            Log::channel('messenger')->debug('Slack webhook: Skipping bot message');

            return response()->json(['ok' => true]);
        }

        // Dispatch async job for processing
        ProcessInboundMessage::dispatch(
            $account->id,
            'slack',
            $request->all()
        );

        return response()->json(['ok' => true]);
    }

    public function handleTelegram(Request $request, string $accountKey): JsonResponse
    {
        /** @var ConnectorAccount $account */
        $account = $request->attributes->get('connector_account');

        Log::channel('messenger')->info('Telegram webhook received', [
            'account_id' => $account->id,
            'update_id' => $request->input('update_id'),
        ]);

        // Check for any supported update type
        $hasMessageContent = $request->has('message')
            || $request->has('edited_message')
            || $request->has('channel_post')
            || $request->has('edited_channel_post')
            || $request->has('callback_query');

        if (! $hasMessageContent) {
            Log::channel('messenger')->debug('Telegram webhook: Skipping unsupported update type');

            return response()->json(['ok' => true]);
        }

        // Skip bot messages to prevent loops
        if ($this->isBotMessage($request, 'telegram')) {
            Log::channel('messenger')->debug('Telegram webhook: Skipping bot message');

            return response()->json(['ok' => true]);
        }

        // Dispatch async job for processing
        ProcessInboundMessage::dispatch(
            $account->id,
            'telegram',
            $request->all()
        );

        return response()->json(['ok' => true]);
    }

    public function handleDiscord(Request $request): JsonResponse
    {
        /** @var ConnectorAccount $account */
        $account = $request->attributes->get('connector_account');
        $type = (int) $request->input('type');

        Log::channel('messenger')->info('Discord webhook received', [
            'account_id' => $account->id,
            'type' => $type,
            'interaction_id' => $request->input('id'),
        ]);

        if ($type === self::DISCORD_TYPE_PING) {
            return response()->json([
                'type' => self::DISCORD_RESPONSE_PONG,
            ]);
        }

        if ($type === self::DISCORD_TYPE_APPLICATION_COMMAND_AUTOCOMPLETE) {
            return response()->json([
                'type' => self::DISCORD_RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT,
                'data' => [
                    'choices' => [],
                ],
            ]);
        }

        if ($this->isBotMessage($request, ConnectorAccount::PROVIDER_DISCORD)) {
            Log::channel('messenger')->debug('Discord webhook: Skipping bot interaction');

            return response()->json([
                'type' => self::DISCORD_RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE,
            ]);
        }

        if (in_array($type, [
            self::DISCORD_TYPE_APPLICATION_COMMAND,
            self::DISCORD_TYPE_MESSAGE_COMPONENT,
            self::DISCORD_TYPE_MODAL_SUBMIT,
        ], true)) {
            ProcessInboundMessage::dispatch(
                $account->id,
                ConnectorAccount::PROVIDER_DISCORD,
                $request->all()
            );
        }

        return response()->json([
            'type' => self::DISCORD_RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE,
        ]);
    }

    public function handleWhatsApp(Request $request): JsonResponse|Response
    {
        // Handle GET verification requests (hub.mode=subscribe)
        if ($request->isMethod('get')) {
            return $this->verifyWhatsApp($request);
        }

        /** @var ConnectorAccount $account */
        $account = $request->attributes->get('connector_account');
        $dispatched = 0;

        Log::channel('messenger')->info('WhatsApp webhook received', [
            'account_id' => $account->id,
            'object' => $request->input('object'),
        ]);

        foreach ($request->input('entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change) || ($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $messages = $value['messages'] ?? [];

                if (! is_array($messages) || $messages === []) {
                    continue;
                }

                foreach ($messages as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    ProcessInboundMessage::dispatch(
                        $account->id,
                        ConnectorAccount::PROVIDER_WHATSAPP,
                        [
                            'entry' => [[
                                'id' => $entry['id'] ?? '',
                                'changes' => [[
                                    'field' => 'messages',
                                    'value' => [
                                        'messaging_product' => $value['messaging_product'] ?? 'whatsapp',
                                        'metadata' => $value['metadata'] ?? [],
                                        'contacts' => $value['contacts'] ?? [],
                                        'messages' => [$message],
                                    ],
                                ]],
                            ]],
                        ]
                    );

                    $dispatched++;
                }
            }
        }

        Log::channel('messenger')->info('WhatsApp webhook processed', [
            'account_id' => $account->id,
            'dispatched_messages' => $dispatched,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Handle WhatsApp webhook verification (GET with hub.mode=subscribe).
     *
     * WhatsApp Cloud API sends a GET request with:
     * - hub_mode: Must be 'subscribe'
     * - hub_verify_token: Must match a configured verify_token
     * - hub_challenge: Challenge string to echo back
     */
    private function verifyWhatsApp(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe') {
            Log::channel('messenger')->warning('WhatsApp verification failed: invalid mode', [
                'mode' => $mode,
            ]);

            return response('Invalid mode', 400);
        }

        // Check verify token against all WhatsApp accounts
        // This is necessary because WhatsApp sends verification requests
        // before the account is fully associated
        $validAccount = ConnectorAccount::where('provider', ConnectorAccount::PROVIDER_WHATSAPP)
            ->get()
            ->first(function (ConnectorAccount $account) use ($verifyToken): bool {
                $credentials = $account->credentials;
                $expectedToken = $credentials['verify_token'] ?? null;

                return $expectedToken !== null && $verifyToken === $expectedToken;
            });

        if ($validAccount !== null) {
            Log::channel('messenger')->info('WhatsApp verification successful', [
                'account_id' => $validAccount->id,
            ]);

            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::channel('messenger')->warning('WhatsApp verification failed: invalid verify token');

        return response('Invalid verify token', 403);
    }

    /**
     * Check if the message is from a bot to prevent loops.
     */
    private function isBotMessage(Request $request, string $provider): bool
    {
        return match ($provider) {
            'slack' => $request->input('event.bot_id') !== null
                || $request->input('event.subtype') === 'bot_message',
            'telegram' => $request->input('message.from.is_bot') === true
                || $request->input('edited_message.from.is_bot') === true
                || $request->input('callback_query.from.is_bot') === true,
            'discord' => $request->input('member.user.bot') === true
                || $request->input('user.bot') === true
                || $request->input('author.bot') === true,
            'whatsapp' => false, // WhatsApp Cloud API doesn't have bot messages
            default => false,
        };
    }
}
