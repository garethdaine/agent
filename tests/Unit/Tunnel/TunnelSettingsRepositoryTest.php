<?php

declare(strict_types=1);

namespace Tests\Unit\Tunnel;

use App\Models\TunnelSetting;
use App\Repositories\TunnelSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelSettingsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TunnelSettingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new TunnelSettingsRepository;
    }

    public function test_get_creates_single_row_if_absent(): void
    {
        $this->assertDatabaseCount('tunnel_settings', 0);

        $setting = $this->repository->get();

        $this->assertInstanceOf(TunnelSetting::class, $setting);
        $this->assertDatabaseCount('tunnel_settings', 1);
        $this->assertSame('unconfigured', $setting->status);
    }

    public function test_get_returns_existing_row(): void
    {
        TunnelSetting::create([
            'settings' => ['hostname' => 'test.example.com'],
            'status' => 'stopped',
        ]);

        $setting = $this->repository->get();

        $this->assertSame('stopped', $setting->status);
        $this->assertSame('test.example.com', $setting->settings['hostname']);
    }

    public function test_get_merges_config_defaults_for_missing_keys(): void
    {
        config(['tunnel.origin_url' => 'http://localhost:8000']);

        TunnelSetting::create([
            'settings' => ['hostname' => 'test.example.com'],
            'status' => 'unconfigured',
        ]);

        $setting = $this->repository->get();
        $merged = $this->repository->getSettings();

        $this->assertSame('test.example.com', $merged['hostname']);
        $this->assertSame('http://localhost:8000', $merged['origin_url']);
    }

    public function test_update_merges_settings_json_not_replaces(): void
    {
        TunnelSetting::create([
            'settings' => ['hostname' => 'old.example.com', 'protocol' => 'http2'],
            'status' => 'unconfigured',
        ]);

        $setting = $this->repository->update(['hostname' => 'new.example.com']);

        $this->assertSame('new.example.com', $setting->settings['hostname']);
        $this->assertSame('http2', $setting->settings['protocol']);
    }

    public function test_update_status_only_changes_status_column(): void
    {
        TunnelSetting::create([
            'settings' => ['hostname' => 'test.example.com'],
            'status' => 'unconfigured',
        ]);

        $setting = $this->repository->updateStatus('active');

        $this->assertSame('active', $setting->status);
        $this->assertSame('test.example.com', $setting->settings['hostname']);
    }

    public function test_get_settings_returns_merged_array(): void
    {
        config([
            'tunnel.origin_url' => 'http://localhost:8000',
            'tunnel.protocol' => 'http2',
            'tunnel.hostname' => '',
        ]);

        TunnelSetting::create([
            'settings' => ['hostname' => 'custom.example.com'],
            'status' => 'unconfigured',
        ]);

        $settings = $this->repository->getSettings();

        // DB value wins over config default
        $this->assertSame('custom.example.com', $settings['hostname']);
        // Config defaults fill in missing keys
        $this->assertSame('http://localhost:8000', $settings['origin_url']);
        $this->assertSame('http2', $settings['protocol']);
    }

    public function test_clear_access_credentials_nullifies_both_keys(): void
    {
        TunnelSetting::create([
            'settings' => [
                'hostname' => 'test.example.com',
                'access_client_id' => 'some-id',
                'access_client_secret' => 'some-secret',
            ],
            'status' => 'active',
        ]);

        $this->repository->clearAccessCredentials();

        $setting = $this->repository->get();
        $this->assertNull($setting->settings['access_client_id']);
        $this->assertNull($setting->settings['access_client_secret']);
        $this->assertSame('test.example.com', $setting->settings['hostname']);
    }

    public function test_concurrent_get_calls_return_same_row(): void
    {
        $setting1 = $this->repository->get();
        $setting2 = $this->repository->get();

        $this->assertSame($setting1->id, $setting2->id);
        $this->assertDatabaseCount('tunnel_settings', 1);
    }
}
