<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TunnelSetting;

class TunnelSettingsRepository
{
    public function get(): TunnelSetting
    {
        return TunnelSetting::firstOrCreate([], [
            'settings' => [],
            'status' => 'unconfigured',
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function update(array $settings, ?string $status = null): TunnelSetting
    {
        $setting = $this->get();

        $merged = array_merge($setting->settings ?? [], $settings);
        $setting->settings = $merged;

        if ($status !== null) {
            $setting->status = $status;
        }

        $setting->save();

        return $setting;
    }

    public function updateStatus(string $status): TunnelSetting
    {
        $setting = $this->get();
        $setting->status = $status;
        $setting->save();

        return $setting;
    }

    public function getStatus(): string
    {
        return $this->get()->status;
    }

    /**
     * Returns settings merged with config defaults for missing keys.
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $setting = $this->get();
        $dbSettings = $setting->settings ?? [];

        $configDefaults = [
            'tunnel_name' => config('tunnel.tunnel_name', ''),
            'tunnel_uuid' => config('tunnel.tunnel_uuid', ''),
            'hostname' => config('tunnel.hostname', ''),
            'origin_url' => config('app.url', config('tunnel.origin_url', 'http://localhost:8000')),
            'protocol' => config('tunnel.protocol', 'http2'),
            'ip_allowlist' => config('tunnel.ip_allowlist', []),
            'cloudflare_access_enabled' => config('tunnel.cloudflare_access_enabled', false),
        ];

        return array_merge($configDefaults, $dbSettings);
    }

    public function clearAccessCredentials(): void
    {
        $setting = $this->get();
        $settings = $setting->settings ?? [];
        $settings['access_client_id'] = null;
        $settings['access_client_secret'] = null;
        $setting->settings = $settings;
        $setting->save();
    }
}
