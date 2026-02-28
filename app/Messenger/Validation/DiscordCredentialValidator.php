<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates Discord credentials based on connection mode.
 *
 * Token Formats:
 * - Bot tokens: Base64-encoded snowflake prefix, followed by timestamp and HMAC
 *   Format: {base64_user_id}.{timestamp_base64}.{hmac}
 *
 * - Application IDs: Snowflake format (numeric string, up to 20 digits)
 *
 * - Public keys: 64-character hex string (Ed25519 public key)
 *
 * Mode Requirements:
 * - Local (Gateway): bot_token, application_id
 * - Webhook: bot_token, application_id, public_key
 *
 * Validation is performed in two phases:
 * 1. Format validation (required fields, basic format checks)
 * 2. API validation (/users/@me call with bot token)
 */
class DiscordCredentialValidator
{
    private const DISCORD_API_BASE = 'https://discord.com/api/v10';

    /**
     * Validate credentials for the given mode.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials, string $mode): ValidationResult
    {
        // Phase 1: Check required fields exist
        $requiredErrors = $this->validateRequiredFields($credentials, $mode);
        if (! empty($requiredErrors)) {
            return ValidationResult::failure($requiredErrors);
        }

        // Phase 2: Validate format
        $formatErrors = $this->validateFormats($credentials, $mode);
        if (! empty($formatErrors)) {
            return ValidationResult::failure($formatErrors);
        }

        // Phase 3: Validate credentials via API
        return $this->validateViaApi($credentials);
    }

    /**
     * Check that all required fields are present for the mode.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function validateRequiredFields(array $credentials, string $mode): array
    {
        $errors = [];

        // Bot token is always required
        if (empty($credentials['bot_token'])) {
            $errors['bot_token'] = 'Bot token is required for Discord integration';
        }

        // Application ID is always required
        if (empty($credentials['application_id'])) {
            $errors['application_id'] = 'Application ID is required for Discord integration';
        }

        // Webhook mode requires public key for Ed25519 verification
        if ($mode === ConnectorAccount::MODE_WEBHOOK) {
            if (empty($credentials['public_key'])) {
                $errors['public_key'] = 'Public key is required for webhook signature verification';
            }
        }

        return $errors;
    }

    /**
     * Validate credential formats.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function validateFormats(array $credentials, string $mode): array
    {
        $errors = [];

        // Validate application_id is numeric (snowflake)
        if (! empty($credentials['application_id'])) {
            if (! ctype_digit((string) $credentials['application_id'])) {
                $errors['application_id'] = 'Application ID must be a numeric snowflake ID';
            }
        }

        // Validate public key format (64 hex characters) for webhook mode
        if ($mode === ConnectorAccount::MODE_WEBHOOK && ! empty($credentials['public_key'])) {
            $publicKey = $credentials['public_key'];
            if (strlen($publicKey) !== 64 || ! ctype_xdigit($publicKey)) {
                $errors['public_key'] = 'Public key must be a 64-character hexadecimal string (Ed25519)';
            }
        }

        return $errors;
    }

    /**
     * Validate credentials by calling Discord's /users/@me API.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function validateViaApi(array $credentials): ValidationResult
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.$credentials['bot_token'],
                'Content-Type' => 'application/json',
            ])->get(self::DISCORD_API_BASE.'/users/@me');

            if (! $response->successful()) {
                $data = $response->json();
                $errorMessage = $data['message'] ?? 'HTTP '.$response->status();

                Log::warning('Discord /users/@me API request failed', [
                    'status' => $response->status(),
                    'message' => $errorMessage,
                ]);

                return ValidationResult::fieldError(
                    'bot_token',
                    'Failed to validate bot token: '.$errorMessage
                );
            }

            $data = $response->json();

            // Verify it's actually a bot account
            if (! ($data['bot'] ?? false)) {
                Log::warning('Discord token is not a bot token');

                return ValidationResult::fieldError(
                    'bot_token',
                    'The provided token is not a bot token'
                );
            }

            // Extract metadata from successful response
            return ValidationResult::success([
                'bot_id' => $data['id'] ?? null,
                'bot_username' => $data['username'] ?? null,
                'discriminator' => $data['discriminator'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'verified' => $data['verified'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error('Discord /users/@me API exception', [
                'exception' => $e->getMessage(),
            ]);

            return ValidationResult::fieldError(
                'bot_token',
                'Failed to validate bot token: '.$e->getMessage()
            );
        }
    }
}
