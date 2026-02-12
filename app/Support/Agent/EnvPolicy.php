<?php

namespace App\Support\Agent;

class EnvPolicy
{
    /**
     * @return array<string, string>
     */
    public function validate(?array $env): array
    {
        if ($env === null) {
            return [];
        }

        $errors = [];

        if (count($env) > (int) config('agent.env_max_keys', 50)) {
            $errors['env_json'] = 'The env_json may not contain more than 50 entries.';
        }

        $forbiddenKeys = array_map('strtoupper', config('agent.forbidden_env_keys', []));
        $forbiddenPattern = (string) config('agent.forbidden_env_key_pattern', '/(SECRET|TOKEN|PASSWORD|PASS|PRIVATE|CREDENTIAL)/i');

        foreach ($env as $key => $value) {
            $errorKey = 'env_json.'.$key;

            if (! is_string($key) || ! preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $key)) {
                $errors[$errorKey] = 'Environment keys must match ^[A-Z][A-Z0-9_]{0,63}$.';

                continue;
            }

            if (in_array(strtoupper($key), $forbiddenKeys, true) || preg_match($forbiddenPattern, $key) === 1) {
                $errors[$errorKey] = 'This environment key is not allowed.';

                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                $errors[$errorKey] = 'Environment values must be scalar.';

                continue;
            }

            $valueLength = strlen((string) $value);

            if ($valueLength > (int) config('agent.env_max_value_length', 1024)) {
                $errors[$errorKey] = 'Environment values may not exceed 1024 characters.';
            }
        }

        $encoded = json_encode($env, JSON_UNESCAPED_UNICODE);

        if ($encoded === false || strlen($encoded) > (int) config('agent.env_max_payload_bytes', 16384)) {
            $errors['env_json'] = 'The env_json payload may not exceed 16KB.';
        }

        return $errors;
    }
}
