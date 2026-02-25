<?php

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Http\Controllers\Controller;
use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ConnectorAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
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

        Log::info('Slack webhook received', [
            'account_id' => $account->id,
            'event_type' => $request->input('event.type'),
            'event_id' => $request->input('event_id'),
        ]);

        // Skip bot messages to prevent loops
        if ($this->isBotMessage($request, 'slack')) {
            Log::debug('Slack webhook: Skipping bot message');

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

        Log::info('Telegram webhook received', [
            'account_id' => $account->id,
            'update_id' => $request->input('update_id'),
        ]);

        // Skip non-message updates
        if (! $request->has('message')) {
            Log::debug('Telegram webhook: Skipping non-message update');

            return response()->json(['ok' => true]);
        }

        // Skip bot messages to prevent loops
        if ($this->isBotMessage($request, 'telegram')) {
            Log::debug('Telegram webhook: Skipping bot message');

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
        // Discord requires immediate response to interactions
        // This is a stub for Phase B
        return response()->json([
            'type' => 1, // PONG for Discord ping
        ]);
    }

    public function handleWhatsApp(Request $request): JsonResponse
    {
        // WhatsApp webhook stub for Phase B
        return response()->json(['ok' => true]);
    }

    /**
     * Check if the message is from a bot to prevent loops.
     */
    private function isBotMessage(Request $request, string $provider): bool
    {
        return match ($provider) {
            'slack' => $request->input('event.bot_id') !== null
                || $request->input('event.subtype') === 'bot_message',
            'telegram' => $request->input('message.from.is_bot') === true,
            'discord' => $request->input('author.bot') === true,
            'whatsapp' => false, // WhatsApp Cloud API doesn't have bot messages
            default => false,
        };
    }
}
