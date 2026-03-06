<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TunnelSetting extends Model
{
    protected $table = 'tunnel_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function getHostname(): ?string
    {
        return $this->settings['hostname'] ?? null;
    }

    public function getTunnelUuid(): ?string
    {
        return $this->settings['tunnel_uuid'] ?? null;
    }

    public function getOriginUrl(): string
    {
        return $this->settings['origin_url'] ?? config('tunnel.origin_url', 'http://localhost:8000');
    }

    public function getProtocol(): string
    {
        return $this->settings['protocol'] ?? config('tunnel.protocol', 'http2');
    }

    public function getIpAllowlist(): array
    {
        return $this->settings['ip_allowlist'] ?? [];
    }

    public function isAccessEnabled(): bool
    {
        return (bool) ($this->settings['cloudflare_access_enabled'] ?? false);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isUnconfigured(): bool
    {
        return $this->status === 'unconfigured';
    }
}
