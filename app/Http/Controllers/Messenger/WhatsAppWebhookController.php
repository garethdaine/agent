<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Jobs\Messenger\ProcessInboundMessage;
use App\Models\ConnectorAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling WhatsApp Cloud API webhooks.
 *
 * WhatsApp webhooks have two modes:
 * 1. GET - Webhook verification (hub.mode=subscribe)
 * 2. POST - Incoming messages and status updates
 *
 * Verification:
 * - Meta sends hub.mode, hub.verify_token, hub.challenge
 * - If verify_token matches, return hub.challenge with 200
 * - If token doesn't match, return 403
 *
 * Message Webhook:
 * - HMAC-SHA256 signature in X-Hub-Signature-256 header
 * - Format: sha256=hex_signature
 * - Key: app_secret
 *
 * Payload Structure:
 * - entry[].changes[].value.messages[] - incoming messages
 * - entry[].changes[].value.statuses[] - delivery status updates
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Handle webhook verification (GET request).
     *
     * Meta sends:
     * - hub.mode = subscribe
     * - hub.verify_token = your configured token
     * - hub.challenge = random string to echo back
     */
    public function verify(Request $request, string $account): Response
    {
        // Find the connector account
        $connectorAccount = ConnectorAccount::find($account);

        if (! $connectorAccount) {
            return response('Not Found', 404);
        }

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::debug('WhatsApp webhook verification attempt', [
            'account_id' => $connectorAccount->id,
            'mode' => $mode,
            'has_token' => ! empty($token),
            'has_challenge' => ! empty($challenge),
        ]);

        // Verify mode is subscribe
        if ($mode !== 'subscribe') {
            Log::warning('WhatsApp webhook verification failed: invalid mode', [
                'account_id' => $connectorAccount->id,
                'mode' => $mode,
            ]);

            return response('Forbidden', 403);
        }

        // Verify token matches
        $expectedToken = $connectorAccount->credentials['verify_token'] ?? null;

        if (! $expectedToken || $token !== $expectedToken) {
            Log::warning('WhatsApp webhook verification failed: token mismatch', [
                'account_id' => $connectorAccount->id,
            ]);

            return response('Forbidden', 403);
        }

        Log::info('WhatsApp webhook verification successful', [
            'account_id' => $connectorAccount->id,
        ]);

        // Return the challenge to confirm subscription
        return response($challenge, 200);
    }

    /**
     * Handle incoming webhook payload (POST request).
     */
    public function handle(Request $request, string $account): Response
    {
        // Find the connector account
        $connectorAccount = ConnectorAccount::find($account);

        if (! $connectorAccount) {
            return response('Not Found', 404);
        }

        // Store account on request for signature verification
        $request->attributes->set('connector_account', $connectorAccount);

        // Verify HMAC-SHA256 signature
        if (! $this->verifySignature($request, $connectorAccount)) {
            return response('Unauthorized', 401);
        }

        Log::info('WhatsApp webhook received', [
            'account_id' => $connectorAccount->id,
            'object' => $request->input('object'),
        ]);

        // Process the webhook payload
        $this->processPayload($request, $connectorAccount);

        return response('OK', 200);
    }

    /**
     * Verify HMAC-SHA256 signature.
     */
    private function verifySignature(Request $request, ConnectorAccount $account): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            Log::debug('WhatsApp signature verification failed: missing header');

            return false;
        }

        $appSecret = $account->credentials['app_secret'] ?? null;

        if (! $appSecret) {
            Log::error('WhatsApp signature verification failed: missing app_secret', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        try {
            $body = $request->getContent();
            $expectedSignature = 'sha256='.hash_hmac('sha256', $body, $appSecret);

            $verified = hash_equals($expectedSignature, $signature);

            if (! $verified) {
                Log::debug('WhatsApp signature verification failed: invalid signature');
            }

            return $verified;
        } catch (\Exception $e) {
            Log::error('WhatsApp signature verification exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process the webhook payload.
     */
    private function processPayload(Request $request, ConnectorAccount $account): void
    {
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if ($change['field'] !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                // Handle messages
                if (! empty($value['messages'])) {
                    $this->processMessages($value, $account);
                }

                // Handle status updates (sent, delivered, read) - don't dispatch jobs
                if (! empty($value['statuses'])) {
                    $this->processStatuses($value, $account);
                }
            }
        }
    }

    /**
     * Process incoming messages.
     *
     * @param  array<string, mixed>  $value
     */
    private function processMessages(array $value, ConnectorAccount $account): void
    {
        $messages = $value['messages'] ?? [];

        foreach ($messages as $message) {
            Log::debug('WhatsApp message received', [
                'account_id' => $account->id,
                'message_id' => $message['id'] ?? null,
                'from' => $message['from'] ?? null,
                'type' => $message['type'] ?? null,
            ]);

            // Dispatch job for each message
            ProcessInboundMessage::dispatch(
                connectorAccountId: (string) $account->id,
                provider: ConnectorAccount::PROVIDER_WHATSAPP,
                payload: [
                    'entry' => [
                        [
                            'id' => $value['metadata']['phone_number_id'] ?? '',
                            'changes' => [
                                [
                                    'field' => 'messages',
                                    'value' => [
                                        'messaging_product' => $value['messaging_product'] ?? 'whatsapp',
                                        'metadata' => $value['metadata'] ?? [],
                                        'contacts' => $value['contacts'] ?? [],
                                        'messages' => [$message],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Process status updates (sent, delivered, read).
     *
     * Status updates are logged but don't dispatch message processing jobs.
     *
     * @param  array<string, mixed>  $value
     */
    private function processStatuses(array $value, ConnectorAccount $account): void
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            Log::debug('WhatsApp status update received', [
                'account_id' => $account->id,
                'message_id' => $status['id'] ?? null,
                'status' => $status['status'] ?? null,
                'recipient_id' => $status['recipient_id'] ?? null,
            ]);

            // Status updates are informational only - no job dispatch needed
            // In a full implementation, you might update message delivery status in database
        }
    }
}
