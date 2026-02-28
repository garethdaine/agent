<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates Slack credentials based on connection mode.
 *
 * Token Formats:
 * - App tokens (xapp-*): Required for Socket Mode local connections
 *   Format: xapp-{version}-{app_id}-{timestamp}-{hash}
 *
 * - Bot tokens (xoxb-*): Required for all API calls
 *   Format: xoxb-{team_id}-{bot_user_id}-{secret}
 *
 * - Signing secrets: Required for webhook signature verification
 *   Format: 32 character hex string
 *
 * Mode Requirements:
 * - Local (Socket Mode): app_token + bot_token
 * - Webhook: bot_token + signing_secret
 *
 * Validation is performed in two phases:
 * 1. Format validation (regex patterns)
 * 2. API validation (auth.test call)
 */
class SlackCredentialValidator
{
    private const SLACK_API_BASE = 'https://slack.com/api';

    /**
     * Regex pattern for app tokens.
     * Format: xapp-{version}-{app_id}-{timestamp}-{hash}
     */
    private const APP_TOKEN_PATTERN = '/^xapp-\d-[A-Z0-9]+-\d+-[a-zA-Z0-9]+$/';

    /**
     * Regex pattern for bot tokens.
     * Format: xoxb-{team_id}-{bot_user_id}-{secret}
     */
    private const BOT_TOKEN_PATTERN = '/^xoxb-\d+-\d+-[a-zA-Z0-9]+$/';

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

        // Phase 2: Validate token formats
        $formatErrors = $this->validateTokenFormats($credentials, $mode);
        if (! empty($formatErrors)) {
            return ValidationResult::failure($formatErrors);
        }

        // Phase 3: Validate tokens via API
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
            $errors['bot_token'] = 'Bot token (xoxb-*) is required for Slack integration';
        }

        if ($mode === ConnectorAccount::MODE_LOCAL) {
            // Local mode (Socket Mode) requires app token
            if (empty($credentials['app_token'])) {
                $errors['app_token'] = 'App token (xapp-*) is required for Socket Mode';
            }
        } else {
            // Webhook mode requires signing secret
            if (empty($credentials['signing_secret'])) {
                $errors['signing_secret'] = 'Signing secret is required for webhook verification';
            }
        }

        return $errors;
    }

    /**
     * Validate token format using regex patterns.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function validateTokenFormats(array $credentials, string $mode): array
    {
        $errors = [];

        // Validate bot token format
        if (! empty($credentials['bot_token'])) {
            if (! preg_match(self::BOT_TOKEN_PATTERN, $credentials['bot_token'])) {
                $errors['bot_token'] = 'Invalid bot token format. Expected format: xoxb-{team_id}-{bot_user_id}-{secret}';
            }
        }

        // Validate app token format (local mode only)
        if ($mode === ConnectorAccount::MODE_LOCAL && ! empty($credentials['app_token'])) {
            if (! preg_match(self::APP_TOKEN_PATTERN, $credentials['app_token'])) {
                $errors['app_token'] = 'Invalid app token format. Expected format: xapp-{version}-{app_id}-{timestamp}-{hash}';
            }
        }

        // Validate signing secret format (webhook mode only)
        if ($mode === ConnectorAccount::MODE_WEBHOOK && ! empty($credentials['signing_secret'])) {
            // Signing secrets are typically 32 character hex strings
            if (strlen($credentials['signing_secret']) < 16) {
                $errors['signing_secret'] = 'Invalid signing secret format. Expected a hex string of at least 16 characters';
            }
        }

        return $errors;
    }

    /**
     * Validate credentials by calling Slack's auth.test API.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function validateViaApi(array $credentials): ValidationResult
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['bot_token'],
                'Content-Type' => 'application/json',
            ])->post(self::SLACK_API_BASE.'/auth.test');

            if (! $response->successful()) {
                Log::warning('Slack auth.test API request failed', [
                    'status' => $response->status(),
                ]);

                return ValidationResult::fieldError(
                    'bot_token',
                    'Failed to validate bot token: HTTP '.$response->status()
                );
            }

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $error = $data['error'] ?? 'Unknown error';
                Log::warning('Slack auth.test API returned error', [
                    'error' => $error,
                ]);

                return ValidationResult::fieldError(
                    'bot_token',
                    'Bot token validation failed: '.$error
                );
            }

            // Extract metadata from successful response
            return ValidationResult::success([
                'bot_username' => $data['user'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'team_name' => $data['team'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'bot_id' => $data['bot_id'] ?? null,
                'url' => $data['url'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Slack auth.test API exception', [
                'exception' => $e->getMessage(),
            ]);

            return ValidationResult::fieldError(
                'bot_token',
                'Failed to validate bot token: '.$e->getMessage()
            );
        }
    }
}
