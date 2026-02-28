<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ConnectorAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Discord webhook interactions.
 *
 * Discord sends interactions to the configured Interactions Endpoint URL.
 * This controller handles:
 * - Ed25519 signature verification (required by Discord)
 * - PING (type 1) - endpoint verification
 * - APPLICATION_COMMAND (type 2) - slash commands
 * - MESSAGE_COMPONENT (type 3) - button/select interactions
 * - APPLICATION_COMMAND_AUTOCOMPLETE (type 4) - autocomplete
 * - MODAL_SUBMIT (type 5) - modal form submissions
 *
 * Response Types:
 * - PONG (1) - for PING
 * - DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE (5) - for async processing
 * - APPLICATION_COMMAND_AUTOCOMPLETE_RESULT (8) - for autocomplete
 *
 * @see https://discord.com/developers/docs/interactions/receiving-and-responding
 */
class DiscordWebhookController extends Controller
{
    // Discord Interaction Types
    private const TYPE_PING = 1;

    private const TYPE_APPLICATION_COMMAND = 2;

    private const TYPE_MESSAGE_COMPONENT = 3;

    private const TYPE_APPLICATION_COMMAND_AUTOCOMPLETE = 4;

    private const TYPE_MODAL_SUBMIT = 5;

    // Discord Response Types
    private const RESPONSE_PONG = 1;

    private const RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE = 5;

    private const RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT = 8;

    /**
     * Handle Discord webhook interaction.
     */
    public function handle(Request $request, string $account): JsonResponse
    {
        // Find the connector account
        $connectorAccount = ConnectorAccount::find($account);

        if (! $connectorAccount) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        // Store account on request for signature verification
        $request->attributes->set('connector_account', $connectorAccount);

        // Verify Ed25519 signature
        if (! $this->verifySignature($request, $connectorAccount)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $type = (int) $request->input('type');

        Log::info('Discord webhook received', [
            'account_id' => $connectorAccount->id,
            'type' => $type,
            'interaction_id' => $request->input('id'),
        ]);

        // Handle based on interaction type
        return match ($type) {
            self::TYPE_PING => $this->handlePing(),
            self::TYPE_APPLICATION_COMMAND => $this->handleApplicationCommand($request, $connectorAccount),
            self::TYPE_MESSAGE_COMPONENT => $this->handleMessageComponent($request, $connectorAccount),
            self::TYPE_APPLICATION_COMMAND_AUTOCOMPLETE => $this->handleAutocomplete($request, $connectorAccount),
            self::TYPE_MODAL_SUBMIT => $this->handleModalSubmit($request, $connectorAccount),
            default => response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]),
        };
    }

    /**
     * Verify Ed25519 signature.
     */
    private function verifySignature(Request $request, ConnectorAccount $account): bool
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');

        if (! $signature || ! $timestamp) {
            Log::debug('Discord signature verification failed: missing headers');

            return false;
        }

        $publicKey = $account->credentials['public_key'] ?? null;

        if (! $publicKey) {
            Log::error('Discord signature verification failed: missing public_key', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        try {
            $body = $request->getContent();
            $message = $timestamp.$body;

            $verified = sodium_crypto_sign_verify_detached(
                hex2bin($signature),
                $message,
                hex2bin($publicKey)
            );

            if (! $verified) {
                Log::debug('Discord signature verification failed: invalid signature');
            }

            return $verified;
        } catch (\Exception $e) {
            Log::error('Discord signature verification exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle PING (type 1) - respond with PONG.
     */
    private function handlePing(): JsonResponse
    {
        Log::debug('Discord PING received, responding with PONG');

        return response()->json(['type' => self::RESPONSE_PONG]);
    }

    /**
     * Handle APPLICATION_COMMAND (type 2) - slash commands.
     */
    private function handleApplicationCommand(Request $request, ConnectorAccount $account): JsonResponse
    {
        // Check if user is a bot
        if ($this->isBot($request)) {
            Log::debug('Discord webhook: Skipping bot interaction');

            return response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]);
        }

        // Dispatch async job for processing
        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $account->id,
            provider: ConnectorAccount::PROVIDER_DISCORD,
            payload: $request->all()
        );

        // Return deferred response (bot will "think" while processing)
        return response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]);
    }

    /**
     * Handle MESSAGE_COMPONENT (type 3) - button/select interactions.
     */
    private function handleMessageComponent(Request $request, ConnectorAccount $account): JsonResponse
    {
        // Check if user is a bot
        if ($this->isBot($request)) {
            Log::debug('Discord webhook: Skipping bot interaction');

            return response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]);
        }

        // Dispatch async job for processing
        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $account->id,
            provider: ConnectorAccount::PROVIDER_DISCORD,
            payload: $request->all()
        );

        return response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]);
    }

    /**
     * Handle APPLICATION_COMMAND_AUTOCOMPLETE (type 4).
     */
    private function handleAutocomplete(Request $request, ConnectorAccount $account): JsonResponse
    {
        // For autocomplete, we need to respond synchronously with choices
        // In a full implementation, this would query available commands/options
        // For now, return an empty choices array

        $data = $request->input('data', []);
        $focusedOption = null;

        // Find the focused option
        foreach ($data['options'] ?? [] as $option) {
            if ($option['focused'] ?? false) {
                $focusedOption = $option;
                break;
            }
        }

        Log::debug('Discord autocomplete request', [
            'account_id' => $account->id,
            'command' => $data['name'] ?? null,
            'focused_option' => $focusedOption['name'] ?? null,
            'value' => $focusedOption['value'] ?? null,
        ]);

        // Return empty choices - this can be enhanced to provide actual suggestions
        return response()->json([
            'type' => self::RESPONSE_APPLICATION_COMMAND_AUTOCOMPLETE_RESULT,
            'data' => [
                'choices' => [],
            ],
        ]);
    }

    /**
     * Handle MODAL_SUBMIT (type 5).
     */
    private function handleModalSubmit(Request $request, ConnectorAccount $account): JsonResponse
    {
        // Check if user is a bot
        if ($this->isBot($request)) {
            Log::debug('Discord webhook: Skipping bot interaction');

            return response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]);
        }

        // Dispatch async job for processing
        ProcessInboundMessage::dispatch(
            connectorAccountId: (string) $account->id,
            provider: ConnectorAccount::PROVIDER_DISCORD,
            payload: $request->all()
        );

        return response()->json(['type' => self::RESPONSE_DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE]);
    }

    /**
     * Check if the interaction is from a bot.
     */
    private function isBot(Request $request): bool
    {
        // Check member.user.bot (guild interactions)
        if ($request->input('member.user.bot') === true) {
            return true;
        }

        // Check user.bot (DM interactions)
        if ($request->input('user.bot') === true) {
            return true;
        }

        return false;
    }
}
