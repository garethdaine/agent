<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Runtime\RuntimeMode;
use Illuminate\Support\Facades\Redis;

class SecurityConfigProvider
{
    private const array IMMUTABLE_KEYS = [
        'content_trust_enabled',
        'injection_detection_enabled',
        'exfiltration_detection',
    ];

    private const float THRESHOLD_FLOOR = 0.9;

    private const int TOKEN_CEILING = 32000;

    private const array DEFAULTS = [
        'content_trust_enabled' => true,
        'injection_detection_enabled' => true,
        'injection_threshold_safe' => 0.3,
        'injection_threshold_standard' => 0.5,
        'injection_threshold_full' => 0.7,
        'injection_action' => 'warn',
        'tool_result_max_tokens' => 8000,
        'strip_html' => true,
        'default_deny_external' => false,
        'exfiltration_detection' => true,
        'messenger_rate_limit' => 20,
        'messenger_group_policy' => 'ignore',
        'prompt_isolation' => true,
        'context_budget_tokens' => 4000,
        'content_wrapping_markers' => false,
        'file_provenance_retention_days' => 30,
        'security_purge_batch_size' => 1000,
        'messenger_high_impact_confirmation' => true,
    ];

    private const array THRESHOLD_KEYS = [
        'injection_threshold_safe',
        'injection_threshold_standard',
        'injection_threshold_full',
    ];

    private const array TOKEN_KEYS = [
        'tool_result_max_tokens',
    ];

    public function get(string $key, ?int $accountId = null): mixed
    {
        if ($this->isImmutable($key)) {
            return true;
        }

        if ($accountId !== null) {
            $cacheKey = "security:config:{$accountId}:{$key}";
            $cached = Redis::connection('cache')->get($cacheKey);

            if ($cached !== null) {
                return $this->clamp($key, $this->castValue($key, $cached));
            }

            $override = \DB::table('account_security_overrides')
                ->where('account_id', $accountId)
                ->where('config_key', $key)
                ->value('config_value');

            if ($override !== null) {
                Redis::connection('cache')->setex($cacheKey, 60, $override);

                return $this->clamp($key, $this->castValue($key, $override));
            }
        }

        $envKey = 'SECURITY_'.strtoupper($key);
        $envValue = env($envKey);

        if ($envValue !== null) {
            return $this->clamp($key, $this->castValue($key, $envValue));
        }

        return self::DEFAULTS[$key] ?? null;
    }

    public function getThreshold(RuntimeMode $mode, ?int $accountId = null): float
    {
        $key = 'injection_threshold_'.strtolower($mode->value);

        return (float) $this->get($key, $accountId);
    }

    public function isImmutable(string $key): bool
    {
        return in_array($key, self::IMMUTABLE_KEYS, true);
    }

    public function flushCache(?int $accountId = null): void
    {
        if ($accountId !== null) {
            foreach (array_keys(self::DEFAULTS) as $key) {
                Redis::connection('cache')->del("security:config:{$accountId}:{$key}");
            }

            return;
        }

        $keys = Redis::connection('cache')->keys('security:config:*');
        if (! empty($keys)) {
            Redis::connection('cache')->del(...$keys);
        }
    }

    private function clamp(string $key, mixed $value): mixed
    {
        if (in_array($key, self::THRESHOLD_KEYS, true) && is_numeric($value)) {
            return min((float) $value, self::THRESHOLD_FLOOR);
        }

        if (in_array($key, self::TOKEN_KEYS, true) && is_numeric($value)) {
            return min((int) $value, self::TOKEN_CEILING);
        }

        return $value;
    }

    private function castValue(string $key, mixed $value): mixed
    {
        $default = self::DEFAULTS[$key] ?? null;

        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($default)) {
            return (int) $value;
        }

        if (is_float($default)) {
            return (float) $value;
        }

        return $value;
    }
}
