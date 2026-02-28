<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates WhatsApp credentials.
 *
 * WhatsApp Cloud API is webhook-only. No local mode is supported.
 *
 * Required Credentials (webhook mode only):
 * - access_token: Graph API access token (typically starts with EAA)
 * - phone_number_id: WhatsApp Business Phone Number ID (numeric string)
 * - app_secret: Meta App Secret for webhook signature verification
 * - verify_token: Webhook verification token (user-defined)
 * - webhook_url: Publicly accessible webhook URL (HTTPS required)
 *
 * Validation is performed in phases:
 * 1. Mode check (webhook only)
 * 2. Required fields check
 * 3. Format validation
 * 4. API validation (/me call with access token)
 * 5. Phone number ID validation (/v18.0/{phone_number_id})
 */
class WhatsAppCredentialValidator
{
    private const GRAPH_API_BASE = 'https://graph.facebook.com/v18.0';

    /**
     * Validate credentials for the given mode.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials, string $mode): ValidationResult
    {
        // Phase 1: Check mode (WhatsApp only supports webhook)
        if ($mode !== ConnectorAccount::MODE_WEBHOOK) {
            return ValidationResult::failure([
                'mode' => 'WhatsApp only supports webhook mode. Local mode is not available for WhatsApp Cloud API.',
            ]);
        }

        // Phase 2: Check required fields exist
        $requiredErrors = $this->validateRequiredFields($credentials);
        if (! empty($requiredErrors)) {
            return ValidationResult::failure($requiredErrors);
        }

        // Phase 3: Validate formats
        $formatErrors = $this->validateFormats($credentials);
        if (! empty($formatErrors)) {
            return ValidationResult::failure($formatErrors);
        }

        // Phase 4 & 5: Validate credentials via API
        return $this->validateViaApi($credentials);
    }

    /**
     * Check that all required fields are present.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function validateRequiredFields(array $credentials): array
    {
        $errors = [];

        if (empty($credentials['access_token'])) {
            $errors['access_token'] = 'Access token is required for WhatsApp integration';
        }

        if (empty($credentials['phone_number_id'])) {
            $errors['phone_number_id'] = 'Phone number ID is required for WhatsApp integration';
        }

        if (empty($credentials['app_secret'])) {
            $errors['app_secret'] = 'App secret is required for webhook signature verification';
        }

        if (empty($credentials['verify_token'])) {
            $errors['verify_token'] = 'Verify token is required for webhook verification';
        }

        if (empty($credentials['webhook_url'])) {
            $errors['webhook_url'] = 'Webhook URL is required for WhatsApp integration';
        }

        return $errors;
    }

    /**
     * Validate credential formats.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function validateFormats(array $credentials): array
    {
        $errors = [];

        // Validate phone_number_id is numeric
        if (! empty($credentials['phone_number_id'])) {
            if (! ctype_digit((string) $credentials['phone_number_id'])) {
                $errors['phone_number_id'] = 'Phone number ID must be a numeric string';
            }
        }

        // Validate webhook_url format
        if (! empty($credentials['webhook_url'])) {
            $url = $credentials['webhook_url'];

            // Check if valid URL
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                $errors['webhook_url'] = 'Webhook URL must be a valid URL';
            }
            // Check if HTTPS
            elseif (! str_starts_with($url, 'https://')) {
                $errors['webhook_url'] = 'Webhook URL must use HTTPS';
            }
        }

        return $errors;
    }

    /**
     * Validate credentials by calling Graph API.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function validateViaApi(array $credentials): ValidationResult
    {
        $accessToken = $credentials['access_token'];
        $phoneNumberId = $credentials['phone_number_id'];

        try {
            // Validate access token via /me endpoint
            $meResponse = Http::withToken($accessToken)
                ->get(self::GRAPH_API_BASE.'/me');

            if (! $meResponse->successful()) {
                $data = $meResponse->json();
                $errorMessage = $data['error']['message'] ?? 'HTTP '.$meResponse->status();

                Log::warning('WhatsApp /me API request failed', [
                    'status' => $meResponse->status(),
                    'message' => $errorMessage,
                ]);

                return ValidationResult::fieldError(
                    'access_token',
                    'Failed to validate access token: '.$errorMessage
                );
            }

            // Validate phone number ID via /{phone_number_id} endpoint
            $phoneResponse = Http::withToken($accessToken)
                ->get(self::GRAPH_API_BASE.'/'.$phoneNumberId);

            if (! $phoneResponse->successful()) {
                $data = $phoneResponse->json();
                $errorMessage = $data['error']['message'] ?? 'HTTP '.$phoneResponse->status();

                Log::warning('WhatsApp phone number validation failed', [
                    'phone_number_id' => $phoneNumberId,
                    'status' => $phoneResponse->status(),
                    'message' => $errorMessage,
                ]);

                return ValidationResult::fieldError(
                    'phone_number_id',
                    'Failed to validate phone number ID: '.$errorMessage
                );
            }

            $phoneData = $phoneResponse->json();

            // Extract metadata from successful response
            return ValidationResult::success([
                'phone_number_id' => $phoneData['id'] ?? $phoneNumberId,
                'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                'verified_name' => $phoneData['verified_name'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp API validation exception', [
                'exception' => $e->getMessage(),
            ]);

            return ValidationResult::fieldError(
                'access_token',
                'Failed to validate credentials: '.$e->getMessage()
            );
        }
    }
}
