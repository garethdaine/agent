<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Support\Agent\InstanceFingerprint;
use App\Support\Agent\LicenseStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LicenseService
{
    private const CACHE_KEY = 'agent_license_status';

    public function __construct(
        private readonly InstanceFingerprint $fingerprint,
    ) {}

    public function validate(): LicenseStatus
    {
        if ($this->shouldBypass()) {
            return LicenseStatus::bypassed();
        }

        $licenseKey = (string) config('agent.license.key', '');

        if ($licenseKey === '') {
            return LicenseStatus::invalid('No license key configured');
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $this->hydrate($cached);
        }

        return $this->validateRemotely($licenseKey);
    }

    public function isValid(): bool
    {
        if ($this->shouldBypass()) {
            return true;
        }

        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) && ($cached['valid'] ?? false);
    }

    public function shouldBypass(): bool
    {
        $host = parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: '';

        foreach ((array) config('agent.license.bypass_domains', []) as $suffix) {
            if ($host === $suffix || str_ends_with($host, $suffix)) {
                return true;
            }
        }

        foreach ((array) config('agent.license.bypass_subdomains', []) as $prefix) {
            if (str_starts_with($host, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function instanceFingerprint(): string
    {
        return $this->fingerprint->generate();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function validateRemotely(string $licenseKey): LicenseStatus
    {
        $url = (string) config('agent.license.validation_url');
        $ttl = (int) config('agent.license.cache_ttl_seconds', 3600);

        try {
            $response = Http::timeout(10)->post($url, [
                'license_key' => $licenseKey,
                'fingerprint' => $this->fingerprint->generate(),
                'domain' => parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: '',
                'app_version' => (string) config('agent.version', '1.0.0'),
            ]);

            if ($response->failed()) {
                return $this->fallbackOrInvalid();
            }

            $data = $response->json();
            $valid = $data['valid'] ?? false;

            $status = $valid
                ? LicenseStatus::valid(
                    plan: $data['license']['plan'] ?? 'standard',
                    expiresAt: $data['license']['expires_at'] ?? null,
                    features: $data['license']['features'] ?? [],
                )
                : LicenseStatus::invalid($data['error'] ?? 'License validation failed');

            Cache::put(self::CACHE_KEY, $this->dehydrate($status), $ttl);

            return $status;
        } catch (\Throwable) {
            return $this->fallbackOrInvalid();
        }
    }

    private function fallbackOrInvalid(): LicenseStatus
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $this->hydrate($cached);
        }

        return LicenseStatus::invalid('License validation service unreachable');
    }

    private function dehydrate(LicenseStatus $status): array
    {
        return [
            'valid' => $status->valid,
            'plan' => $status->plan,
            'expires_at' => $status->expiresAt,
            'features' => $status->features,
            'error' => $status->error,
        ];
    }

    private function hydrate(array $data): LicenseStatus
    {
        return new LicenseStatus(
            valid: $data['valid'] ?? false,
            plan: $data['plan'] ?? '',
            expiresAt: $data['expires_at'] ?? null,
            features: $data['features'] ?? [],
            error: $data['error'] ?? null,
        );
    }
}
