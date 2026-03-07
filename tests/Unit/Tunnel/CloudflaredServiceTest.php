<?php

declare(strict_types=1);

namespace Tests\Unit\Tunnel;

use App\Services\Tunnel\CloudflaredService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CloudflaredServiceTest extends TestCase
{
    private CloudflaredService $service;

    private const FAKE_BINARY_PATH = '/usr/local/bin/cloudflared';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tunnel.cloudflared_binary' => 'cloudflared',
            'tunnel.cloudflared_fallback_paths' => [],
        ]);
        $this->service = new CloudflaredService;
    }

    public function test_is_binary_installed_returns_true_when_found(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: '/usr/local/bin/cloudflared', exitCode: 0),
        ]);

        $this->assertTrue($this->service->isBinaryInstalled());
    }

    public function test_is_binary_installed_returns_false_when_missing(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: '', exitCode: 1),
        ]);

        $this->assertFalse($this->service->isBinaryInstalled());
    }

    public function test_get_version_parses_semver_from_stdout(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' version' => Process::result(
                output: 'cloudflared version 2024.6.1 (built 2024-06-01-1200 lgo1.22.3)',
                exitCode: 0,
            ),
        ]);

        $this->assertSame('2024.6.1', $this->service->getVersion());
    }

    public function test_get_version_returns_null_on_failure(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: '', exitCode: 1),
            'cloudflared version' => Process::result(output: '', exitCode: 1),
        ]);

        $this->assertNull($this->service->getVersion());
    }

    public function test_is_version_compatible_returns_warning_for_old_version(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' version' => Process::result(
                output: 'cloudflared version 2023.12.0 (built 2023-12-01)',
                exitCode: 0,
            ),
        ]);

        $result = $this->service->isVersionCompatible();

        $this->assertFalse($result['compatible']);
        $this->assertSame('2023.12.0', $result['installed_version']);
        $this->assertSame('2024.1.0', $result['min_version']);
        $this->assertNotNull($result['warning']);
    }

    public function test_is_version_compatible_returns_compatible_for_current(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' version' => Process::result(
                output: 'cloudflared version 2024.6.1 (built 2024-06-01)',
                exitCode: 0,
            ),
        ]);

        $result = $this->service->isVersionCompatible();

        $this->assertTrue($result['compatible']);
        $this->assertSame('2024.6.1', $result['installed_version']);
        $this->assertNull($result['warning']);
    }

    public function test_validate_credentials_all_pass(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' version' => Process::result(output: 'cloudflared version 2024.6.1', exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel list --output json' => Process::result(output: '[]', exitCode: 0),
        ]);

        $certPath = $this->fakeCertPem();

        $result = $this->service->validateCredentials($certPath);

        $this->assertTrue($result['binary']);
        $this->assertTrue($result['credentials']);
        $this->assertTrue($result['functional']);
        $this->assertSame('2024.6.1', $result['version']);
    }

    public function test_validate_credentials_binary_missing(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: '', exitCode: 1),
        ]);

        $result = $this->service->validateCredentials('/nonexistent/cert.pem');

        $this->assertFalse($result['binary']);
        $this->assertFalse($result['credentials']);
        $this->assertFalse($result['functional']);
    }

    public function test_validate_credentials_cert_missing(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' version' => Process::result(output: 'cloudflared version 2024.6.1', exitCode: 0),
        ]);

        $result = $this->service->validateCredentials('/nonexistent/cert.pem');

        $this->assertTrue($result['binary']);
        $this->assertFalse($result['credentials']);
        $this->assertFalse($result['functional']);
    }

    public function test_validate_credentials_tunnel_list_fails(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' version' => Process::result(output: 'cloudflared version 2024.6.1', exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel list --output json' => Process::result(output: 'error: unauthorized', exitCode: 1),
        ]);

        $certPath = $this->fakeCertPem();

        $result = $this->service->validateCredentials($certPath);

        $this->assertTrue($result['binary']);
        $this->assertTrue($result['credentials']);
        $this->assertFalse($result['functional']);
    }

    public function test_start_login_parses_auth_url_from_stdout(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel login' => Process::result(
                output: "Please open the following URL and log in with your Cloudflare account:\n\nhttps://dash.cloudflare.com/argotunnel/abc123?callback=def456\n\nLeave cloudflared running to download the certificate automatically.",
                exitCode: 0,
            ),
        ]);

        $result = $this->service->startLogin();

        $this->assertSame('https://dash.cloudflare.com/argotunnel/abc123?callback=def456', $result['auth_url']);
        $this->assertTrue($result['success']);
    }

    public function test_start_login_returns_error_when_no_url_found(): void
    {
        Process::fake([
            'cloudflared tunnel login' => Process::result(
                output: 'Some unexpected output without a URL.',
                exitCode: 1,
            ),
        ]);

        $result = $this->service->startLogin();

        $this->assertFalse($result['success']);
        $this->assertNull($result['auth_url']);
        $this->assertNotNull($result['error']);
    }

    public function test_create_tunnel_parses_uuid_from_json(): void
    {
        $tunnelJson = json_encode([
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'name' => 'my-tunnel',
        ]);

        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            "*'create' '--output' 'json' 'my-tunnel'*" => Process::result(
                output: $tunnelJson,
                exitCode: 0,
            ),
        ]);

        $result = $this->service->createTunnel('my-tunnel');

        $this->assertTrue($result['success']);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $result['id']);
        $this->assertSame('my-tunnel', $result['name']);
    }

    public function test_create_tunnel_handles_failure(): void
    {
        Process::fake([
            "*'create' '--output' 'json' 'bad-tunnel'*" => Process::result(
                output: 'Error: tunnel with name already exists',
                exitCode: 1,
            ),
        ]);

        $result = $this->service->createTunnel('bad-tunnel');

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    public function test_delete_tunnel_returns_true_on_success(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel delete a1b2c3d4-e5f6-7890-abcd-ef1234567890' => Process::result(
                output: 'Successfully deleted tunnel a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                exitCode: 0,
            ),
        ]);

        $this->assertTrue($this->service->deleteTunnel('a1b2c3d4-e5f6-7890-abcd-ef1234567890'));
    }

    public function test_delete_tunnel_returns_false_on_failure(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel delete nonexistent' => Process::result(
                output: 'Error: tunnel not found',
                exitCode: 1,
            ),
        ]);

        $this->assertFalse($this->service->deleteTunnel('nonexistent'));
    }

    public function test_build_run_command_constructs_correct_args(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
        ]);

        $result = $this->service->buildRunCommand(
            uuid: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            originUrl: 'http://localhost:8000',
            protocol: 'http2',
            ipAllowlist: null,
            metricsPort: 20241,
        );

        $command = $result['command'];
        $this->assertStringContainsString('cloudflared', implode(' ', $command));
        $this->assertContains('tunnel', $command);
        $this->assertContains('run', $command);
        $this->assertContains('--url', $command);
        $this->assertContains('http://localhost:8000', $command);
        $this->assertContains('--protocol', $command);
        $this->assertContains('http2', $command);
        $this->assertContains('--metrics', $command);
        $this->assertContains('localhost:20241', $command);
        $this->assertContains('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $command);
        $this->assertNull($result['config_path']);
    }

    public function test_build_run_command_includes_ip_allowlist_in_config(): void
    {
        $result = $this->service->buildRunCommand(
            uuid: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            originUrl: 'http://localhost:8000',
            protocol: 'http2',
            ipAllowlist: ['192.168.1.0/24', '10.0.0.0/8'],
            metricsPort: 20241,
            hostname: 'agent.example.com',
        );

        $this->assertNotNull($result['config_path']);
        $this->assertFileExists($result['config_path']);

        $configContent = file_get_contents($result['config_path']);
        $this->assertStringContainsString('agent.example.com', $configContent);
        $this->assertStringContainsString('192.168.1.0/24', $configContent);
        $this->assertStringContainsString('10.0.0.0/8', $configContent);
        $this->assertStringContainsString('ingress', $configContent);

        $command = $result['command'];
        $this->assertContains('--config', $command);

        // Cleanup
        @unlink($result['config_path']);
    }

    public function test_build_run_command_normalizes_url_hostname_to_plain_host(): void
    {
        $result = $this->service->buildRunCommand(
            uuid: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            originUrl: 'https://agent.test',
            protocol: 'http2',
            ipAllowlist: ['10.0.0.0/8'],
            metricsPort: 20241,
            hostname: 'https://agent.garethdaine.com',
        );

        $this->assertNotNull($result['config_path']);
        $configContent = file_get_contents($result['config_path']);
        $this->assertStringContainsString('hostname: agent.garethdaine.com', $configContent);

        @unlink($result['config_path']);
    }

    public function test_build_run_command_without_ip_allowlist(): void
    {
        $result = $this->service->buildRunCommand(
            uuid: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            originUrl: 'http://localhost:8000',
            protocol: 'quic',
            ipAllowlist: null,
            metricsPort: 20241,
        );

        $this->assertNull($result['config_path']);
        $this->assertContains('quic', $result['command']);
    }

    public function test_route_dns_success_returns_cname_target(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel route dns a1b2c3d4-e5f6-7890-abcd-ef1234567890 agent.example.com' => Process::result(
                output: 'Successfully routed DNS to a1b2c3d4-e5f6-7890-abcd-ef1234567890.cfargotunnel.com',
                exitCode: 0,
            ),
        ]);

        $result = $this->service->routeDns('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'agent.example.com');

        $this->assertTrue($result['success']);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890.cfargotunnel.com', $result['cname_target']);
        $this->assertNull($result['error']);
    }

    public function test_route_dns_failure_returns_manual_instructions(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' tunnel route dns a1b2c3d4-e5f6-7890-abcd-ef1234567890 agent.example.com' => Process::result(
                output: 'Error: You don\'t have permission to modify DNS records',
                exitCode: 1,
            ),
        ]);

        $result = $this->service->routeDns('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'agent.example.com');

        $this->assertFalse($result['success']);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890.cfargotunnel.com', $result['cname_target']);
        $this->assertNotNull($result['error']);
        $this->assertNotNull($result['manual_instructions']);
    }

    public function test_generate_access_service_token_parses_credentials(): void
    {
        Process::fake([
            'which cloudflared' => Process::result(output: self::FAKE_BINARY_PATH, exitCode: 0),
            self::FAKE_BINARY_PATH.' access service-token --hostname agent.example.com' => Process::result(
                output: "Client ID: abc123def456.access\nClient Secret: secret-value-here-789xyz",
                exitCode: 0,
            ),
        ]);

        $result = $this->service->generateAccessServiceToken('agent.example.com');

        $this->assertTrue($result['success']);
        $this->assertSame('abc123def456.access', $result['client_id']);
        $this->assertSame('secret-value-here-789xyz', $result['client_secret']);
    }

    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            @unlink($path);
            @rmdir(dirname($path));
        }

        parent::tearDown();
    }

    private function fakeCertPem(): string
    {
        $path = sys_get_temp_dir().'/cloudflared_test_'.uniqid().'/cert.pem';
        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, 'fake-cert-content');
        $this->tempPaths[] = $path;

        return $path;
    }
}
