<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates Telegram bot credentials for both local and webhook modes.
 *
 * Validation Process:
 * 1. Check required fields based on mode
 * 2. Validate token format (numeric:alphanumeric)
 * 3. Test token via getMe API call
 * 4. Return bot info on success
 *
 * Mode Requirements:
 * - Local mode: bot_token only
 * - Webhook mode: bot_token + webhook_url
 *
 * Token Format:
 * Telegram bot tokens follow the pattern: {numeric_bot_id}:{alphanumeric_secret}
 * Example: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789
 */
class TelegramCredentialValidator
{
    private const TELEGRAM_API_BASE = 'https://api.telegram.org';

    /**
     * Bot token format: numeric_id:alphanumeric_secret
     * The alphanumeric part can include letters, digits, underscore, and hyphen.
     */
    private const BOT_TOKEN_PATTERN = '/^\d+:[A-Za-z0-9_-]+$/';

    /**
     * Validate Telegram credentials for the given mode.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials, string $mode): ValidationResult
    {
        $errors = [];

        // Phase 1: Validate required fields based on mode
        $requiredFieldErrors = $this->validateRequiredFields($credentials, $mode);
        if (! empty($requiredFieldErrors)) {
            $errors = array_merge($errors, $requiredFieldErrors);
        }

        // Phase 2: Validate token format (only if provided)
        $botToken = $credentials['bot_token'] ?? null;
        if ($botToken !== null && $botToken !== '') {
            $formatError = $this->validateTokenFormat($botToken);
            if ($formatError !== null) {
                $errors['bot_token'] = $formatError;
            }
        }

        // Phase 2b: Validate webhook URL format (only for webhook mode if provided)
        if ($mode === ConnectorAccount::MODE_WEBHOOK) {
            $webhookUrl = $credentials['webhook_url'] ?? null;
            if ($webhookUrl !== null && $webhookUrl !== '') {
                $urlError = $this->validateWebhookUrl($webhookUrl);
                if ($urlError !== null) {
                    $errors['webhook_url'] = $urlError;
                }
            }
        }

        // If there are format/required errors, return early
        if (! empty($errors)) {
            return ValidationResult::failure($errors);
        }

        // Phase 3: Validate via getMe API call
        return $this->validateViaApi($botToken);
    }

    /**
     * Validate required fields based on connection mode.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function validateRequiredFields(array $credentials, string $mode): array
    {
        $errors = [];

        // bot_token required for both modes
        $botToken = $credentials['bot_token'] ?? null;
        if (empty($botToken)) {
            $errors['bot_token'] = 'Bot token is required';
        }

        // webhook_url required for webhook mode
        if ($mode === ConnectorAccount::MODE_WEBHOOK) {
            $webhookUrl = $credentials['webhook_url'] ?? null;
            if (empty($webhookUrl)) {
                $errors['webhook_url'] = 'Webhook URL is required for webhook mode';
            }
        }

        return $errors;
    }

    /**
     * Validate bot token format.
     *
     * @return string|null Error message if invalid, null if valid
     */
    private function validateTokenFormat(string $token): ?string
    {
        if (! preg_match(self::BOT_TOKEN_PATTERN, $token)) {
            return 'Bot token format is invalid. Expected format: numeric_id:alphanumeric_secret';
        }

        return null;
    }

    /**
     * Validate webhook URL format.
     *
     * @return string|null Error message if invalid, null if valid
     */
    private function validateWebhookUrl(string $url): ?string
    {
        // Must be a valid URL
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'Webhook URL is not a valid URL';
        }

        // Must use HTTPS
        $parsedUrl = parse_url($url);
        $scheme = $parsedUrl['scheme'] ?? '';
        if (strtolower($scheme) !== 'https') {
            return 'Webhook URL must use HTTPS';
        }

        return null;
    }

    /**
     * Validate token by calling the Telegram getMe API.
     */
    private function validateViaApi(string $botToken): ValidationResult
    {
        $url = sprintf('%s/bot%s/getMe', self::TELEGRAM_API_BASE, $botToken);

        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                Log::warning('Telegram getMe API HTTP error', [
                    'status' => $response->status(),
                ]);

                return ValidationResult::fieldError(
                    'bot_token',
                    'Failed to validate bot token: HTTP error '.$response->status()
                );
            }

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $error = $data['description'] ?? 'Unknown error';

                Log::warning('Telegram getMe API error', [
                    'error' => $error,
                    'error_code' => $data['error_code'] ?? null,
                ]);

                return ValidationResult::fieldError('bot_token', $error);
            }

            $result = $data['result'] ?? [];

            // Verify this is actually a bot account
            if (! ($result['is_bot'] ?? false)) {
                return ValidationResult::fieldError(
                    'bot_token',
                    'The provided token is not a bot account'
                );
            }

            // Extract metadata
            $metadata = [
                'bot_id' => $result['id'] ?? null,
                'bot_username' => $result['username'] ?? null,
                'bot_first_name' => $result['first_name'] ?? null,
                'can_join_groups' => $result['can_join_groups'] ?? false,
                'can_read_all_group_messages' => $result['can_read_all_group_messages'] ?? false,
                'supports_inline_queries' => $result['supports_inline_queries'] ?? false,
            ];

            Log::info('Telegram bot validated successfully', [
                'bot_id' => $metadata['bot_id'],
                'bot_username' => $metadata['bot_username'],
            ]);

            return ValidationResult::success($metadata);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Telegram getMe API connection error', [
                'error' => $e->getMessage(),
            ]);

            return ValidationResult::fieldError(
                'bot_token',
                'Failed to connect to Telegram API: '.$e->getMessage()
            );
        } catch (\Exception $e) {
            Log::error('Telegram getMe API exception', [
                'error' => $e->getMessage(),
            ]);

            return ValidationResult::fieldError(
                'bot_token',
                'Failed to validate bot token: '.$e->getMessage()
            );
        }
    }
}
