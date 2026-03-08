<?php

declare(strict_types=1);

namespace App\Support\Connectors;

class ConnectorManifestValidator
{
    private const REQUIRED_FIELDS = [
        'name', 'display_name', 'description', 'version',
        'auth_type', 'auth_config', 'base_url', 'rate_limits', 'actions',
    ];

    private const VALID_AUTH_TYPES = [
        'oauth2_authorization_code',
        'oauth2_client_credentials',
        'api_key',
        'api_key_secret',
        'basic',
        'custom_header',
    ];

    private const VALID_ACTION_METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

    private const OAUTH2_TYPES = ['oauth2_authorization_code', 'oauth2_client_credentials'];

    public function validate(array $manifest): ManifestValidationResult
    {
        $errors = [];
        $warnings = [];

        $this->validateRequiredFields($manifest, $errors);

        if (! empty($errors)) {
            return ManifestValidationResult::failure($errors, $warnings);
        }

        $this->validateName($manifest['name'], $errors);
        $this->validateAuthType($manifest['auth_type'], $errors);
        $this->validateAuthConfig($manifest, $errors);
        $this->validateRateLimits($manifest['rate_limits'], $errors);
        $this->validateActions($manifest['actions'], $errors);

        if (! empty($errors)) {
            return ManifestValidationResult::failure($errors, $warnings);
        }

        return ManifestValidationResult::success($warnings);
    }

    private function validateRequiredFields(array $manifest, array &$errors): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $manifest) || $manifest[$field] === null || $manifest[$field] === '') {
                $errors[] = "Missing required field: {$field}";
            }
        }
    }

    private function validateName(string $name, array &$errors): void
    {
        if (strlen($name) > 64) {
            $errors[] = 'Name must not exceed 64 characters.';
        }

        if (! preg_match('/^[a-z0-9\-]+$/', $name)) {
            $errors[] = 'Name must be lowercase alphanumeric with hyphens only.';
        }
    }

    private function validateAuthType(string $authType, array &$errors): void
    {
        if (! in_array($authType, self::VALID_AUTH_TYPES, true)) {
            $errors[] = "Invalid auth_type: {$authType}. Must be one of: ".implode(', ', self::VALID_AUTH_TYPES);
        }
    }

    private function validateAuthConfig(array $manifest, array &$errors): void
    {
        $authType = $manifest['auth_type'];
        $authConfig = $manifest['auth_config'];

        if (! is_array($authConfig)) {
            $errors[] = 'auth_config must be an array.';

            return;
        }

        if (in_array($authType, self::OAUTH2_TYPES, true)) {
            if (empty($authConfig['authorization_url'])) {
                $errors[] = 'OAuth2 auth_type requires authorization_url in auth_config.';
            }
            if (empty($authConfig['token_url'])) {
                $errors[] = 'OAuth2 auth_type requires token_url in auth_config.';
            }
        }
    }

    private function validateRateLimits(mixed $rateLimits, array &$errors): void
    {
        if (! is_array($rateLimits)) {
            $errors[] = 'rate_limits must be an array.';

            return;
        }

        $requiredKeys = ['requests_per_minute', 'daily_limit', 'concurrent_limit'];

        foreach ($requiredKeys as $key) {
            if (! isset($rateLimits[$key])) {
                $errors[] = "rate_limits.{$key} is required.";
            } elseif (! is_int($rateLimits[$key]) || $rateLimits[$key] <= 0) {
                $errors[] = "rate_limits.{$key} must be a positive integer.";
            }
        }
    }

    private function validateActions(mixed $actions, array &$errors): void
    {
        if (! is_array($actions)) {
            $errors[] = 'actions must be an array.';

            return;
        }

        $seenNames = [];

        foreach ($actions as $index => $action) {
            if (! is_array($action)) {
                $errors[] = "Action at index {$index} must be an array.";

                continue;
            }

            if (empty($action['name'])) {
                $errors[] = "Action at index {$index} is missing required field: name.";
            }

            if (empty($action['method'])) {
                $errors[] = "Action at index {$index} is missing required field: method.";
            } elseif (! in_array($action['method'], self::VALID_ACTION_METHODS, true)) {
                $errors[] = "Action '{$action['name']}' has invalid method: {$action['method']}. Must be one of: ".implode(', ', self::VALID_ACTION_METHODS);
            }

            if (empty($action['path'])) {
                $errors[] = "Action at index {$index} is missing required field: path.";
            }

            if (! empty($action['name'])) {
                if (in_array($action['name'], $seenNames, true)) {
                    $errors[] = "Duplicate action name: {$action['name']}.";
                }
                $seenNames[] = $action['name'];
            }
        }
    }
}
