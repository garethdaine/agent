<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use Illuminate\Support\Facades\Process;

class CloudflaredService
{
    public const MIN_VERSION = '2024.1.0';

    private string $binary;

    public function __construct()
    {
        $this->binary = config('tunnel.cloudflared_binary', 'cloudflared');
    }

    public function isBinaryInstalled(): bool
    {
        return $this->getBinaryPath() !== null;
    }

    public function getBinaryPath(): ?string
    {
        $binary = $this->binary;

        if (str_contains($binary, '/')) {
            return is_executable($binary) ? $binary : null;
        }

        $result = Process::run('which '.$binary);
        if ($result->successful()) {
            return trim($result->output());
        }

        $fallbackPaths = config('tunnel.cloudflared_fallback_paths', []);
        foreach ($fallbackPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function getVersion(): ?string
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $result = Process::run($binary.' version');

        if (! $result->successful()) {
            return null;
        }

        return $this->parseVersion($result->output());
    }

    public function isVersionCompatible(): array
    {
        $version = $this->getVersion();

        if ($version === null) {
            return [
                'compatible' => false,
                'installed_version' => null,
                'min_version' => self::MIN_VERSION,
                'warning' => 'Could not determine cloudflared version.',
            ];
        }

        $compatible = $this->compareVersions($version, self::MIN_VERSION);

        return [
            'compatible' => $compatible,
            'installed_version' => $version,
            'min_version' => self::MIN_VERSION,
            'warning' => $compatible ? null : "Installed cloudflared version {$version} is below the minimum recommended version ".self::MIN_VERSION.'.',
        ];
    }

    public function isAuthenticated(?string $certPath = null): bool
    {
        return file_exists($certPath ?? $this->getCertPath());
    }

    public function getCertPath(): string
    {
        $configured = config('tunnel.cert_path');
        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '/root';

        return $home.'/.cloudflared/cert.pem';
    }

    public function listTunnels(): array
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $result = Process::run($binary.' tunnel list --output json');

        if (! $result->successful()) {
            return [];
        }

        return json_decode($result->output(), true) ?? [];
    }

    public function validateCredentials(?string $certPath = null): array
    {
        $certPath = $certPath ?? $this->getCertPath();

        $binaryInstalled = $this->isBinaryInstalled();
        if (! $binaryInstalled) {
            return [
                'binary' => false,
                'credentials' => false,
                'functional' => false,
                'version' => null,
                'version_warning' => null,
            ];
        }

        $version = $this->getVersion();
        $versionCompat = $this->isVersionCompatible();

        $credentialsExist = file_exists($certPath);
        if (! $credentialsExist) {
            return [
                'binary' => true,
                'credentials' => false,
                'functional' => false,
                'version' => $version,
                'version_warning' => $versionCompat['warning'],
            ];
        }

        $binary = $this->getBinaryPath() ?? $this->binary;
        $tunnelListResult = Process::run($binary.' tunnel list --output json');
        $functional = $tunnelListResult->successful();

        return [
            'binary' => true,
            'credentials' => true,
            'functional' => $functional,
            'version' => $version,
            'version_warning' => $versionCompat['warning'],
        ];
    }

    public function startLogin(): array
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $result = Process::run($binary.' tunnel login');

        $output = $result->output();

        if (preg_match('#(https://dash\.cloudflare\.com[^\s]+)#', $output, $matches)) {
            return [
                'success' => true,
                'auth_url' => $matches[1],
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'auth_url' => null,
            'error' => 'Could not parse authentication URL from cloudflared output.',
        ];
    }

    public function pollLoginComplete(?string $certPath = null): bool
    {
        return file_exists($certPath ?? $this->getCertPath());
    }

    public function createTunnel(string $name): array
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $result = Process::run([$binary, 'tunnel', 'create', '--output', 'json', $name]);

        if (! $result->successful()) {
            return [
                'success' => false,
                'id' => null,
                'name' => null,
                'error' => trim($result->output() ?: $result->errorOutput()),
            ];
        }

        $data = json_decode($result->output(), true);

        if (! $data || ! isset($data['id'])) {
            return [
                'success' => false,
                'id' => null,
                'name' => null,
                'error' => 'Could not parse tunnel creation response.',
            ];
        }

        return [
            'success' => true,
            'id' => $data['id'],
            'name' => $data['name'] ?? $name,
            'error' => null,
        ];
    }

    public function deleteTunnel(string $uuidOrName): bool
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $result = Process::run($binary.' tunnel delete '.$uuidOrName);

        return $result->successful();
    }

    public function findAvailableMetricsPort(): int
    {
        $preferred = (int) config('tunnel.metrics_port', 20241);
        $rangeEnd = (int) config('tunnel.metrics_port_range_end', 20249);

        for ($port = $preferred; $port <= $rangeEnd; $port++) {
            $socket = @stream_socket_server(
                "tcp://127.0.0.1:{$port}",
                $errno,
                $errstr,
                STREAM_SERVER_BIND
            );
            if (is_resource($socket)) {
                fclose($socket);

                return $port;
            }
        }

        return $preferred;
    }

    public function buildRunCommand(
        string $uuid,
        string $originUrl,
        string $protocol,
        ?array $ipAllowlist,
        ?int $metricsPort = null,
        ?string $hostname = null,
    ): array {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $port = $metricsPort ?? $this->findAvailableMetricsPort();
        $command = [
            $binary,
            'tunnel',
            '--protocol', $protocol,
            '--metrics', 'localhost:'.$port,
        ];

        $useConfig = ! empty($ipAllowlist) || str_starts_with($originUrl, 'https://');
        if ($useConfig) {
            $configPath = $this->generateIngressConfig($uuid, $originUrl, $ipAllowlist, $hostname);
            $command[] = '--config';
            $command[] = $configPath;
        } else {
            $command[] = '--url';
            $command[] = $originUrl;
        }

        $command[] = 'run';
        $command[] = $uuid;

        return [
            'command' => $command,
            'config_path' => $useConfig ? $configPath ?? null : null,
        ];
    }

    public function routeDns(string $tunnelUuid, string $hostname): array
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $cnameTarget = $tunnelUuid.'.cfargotunnel.com';

        $result = Process::run($binary.' tunnel route dns '.$tunnelUuid.' '.$hostname);

        if ($result->successful()) {
            return [
                'success' => true,
                'cname_target' => $cnameTarget,
                'error' => null,
                'manual_instructions' => null,
            ];
        }

        return [
            'success' => false,
            'cname_target' => $cnameTarget,
            'error' => trim($result->output() ?: $result->errorOutput()),
            'manual_instructions' => "Add a CNAME record for '{$hostname}' pointing to '{$cnameTarget}' in your Cloudflare DNS settings.",
        ];
    }

    public function generateAccessServiceToken(string $hostname): array
    {
        $binary = $this->getBinaryPath() ?? $this->binary;
        $result = Process::run($binary.' access service-token --hostname '.$hostname);

        if (! $result->successful()) {
            return [
                'success' => false,
                'client_id' => null,
                'client_secret' => null,
                'error' => trim($result->output() ?: $result->errorOutput()),
            ];
        }

        $output = $result->output();
        $clientId = null;
        $clientSecret = null;

        if (preg_match('/Client ID:\s*(\S+)/', $output, $matches)) {
            $clientId = $matches[1];
        }

        if (preg_match('/Client Secret:\s*(\S+)/', $output, $matches)) {
            $clientSecret = $matches[1];
        }

        return [
            'success' => true,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'error' => null,
        ];
    }

    public function generateIngressConfig(string $uuid, string $originUrl, ?array $ipAllowlist, ?string $hostname = null): string
    {
        $config = [
            'tunnel' => $uuid,
            'ingress' => [],
        ];

        $rule = [
            'service' => $originUrl,
        ];
        if (! empty($hostname)) {
            $rule['hostname'] = $this->normalizeHostnameForIngress($hostname);
        }

        $originRequest = [];
        $originHost = parse_url($originUrl, PHP_URL_HOST);
        if (! empty($originHost)) {
            $originRequest['httpHostHeader'] = $originHost;
        }
        if (str_starts_with($originUrl, 'https://')) {
            $originRequest['noTLSVerify'] = true;
        }
        if (! empty($ipAllowlist)) {
            $originRequest['ipRules'] = array_map(fn (string $cidr) => [
                'prefix' => $cidr,
                'allow' => true,
            ], $ipAllowlist);
        }
        if (! empty($originRequest)) {
            $rule['originRequest'] = $originRequest;
        }

        $config['ingress'][] = $rule;

        // Catch-all rule required by cloudflared
        $config['ingress'][] = [
            'service' => 'http_status:404',
        ];

        $yaml = $this->arrayToYaml($config);

        $configDir = storage_path('app/tunnel');
        if (! is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $configPath = $configDir.'/config.yml';
        file_put_contents($configPath, $yaml);

        return $configPath;
    }

    private function normalizeHostnameForIngress(string $hostname): string
    {
        $hostname = trim($hostname);
        if ($hostname === '') {
            return $hostname;
        }
        $url = str_starts_with($hostname, 'http') ? $hostname : 'https://'.$hostname;
        $host = parse_url($url, PHP_URL_HOST);

        return $host ?? $hostname;
    }

    private function parseVersion(string $output): ?string
    {
        if (preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function compareVersions(string $installed, string $minimum): bool
    {
        return version_compare($installed, $minimum, '>=');
    }

    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                if (is_array($value)) {
                    $yaml .= $prefix.'- ';
                    $first = true;
                    foreach ($value as $k => $v) {
                        if ($first) {
                            if (is_array($v)) {
                                $yaml .= $k.":\n".$this->arrayToYaml($v, $indent + 2);
                            } else {
                                $yaml .= $k.': '.$this->yamlValue($v)."\n";
                            }
                            $first = false;
                        } else {
                            if (is_array($v)) {
                                $yaml .= $prefix.'  '.$k.":\n".$this->arrayToYaml($v, $indent + 2);
                            } else {
                                $yaml .= $prefix.'  '.$k.': '.$this->yamlValue($v)."\n";
                            }
                        }
                    }
                } else {
                    $yaml .= $prefix.'- '.$this->yamlValue($value)."\n";
                }
            } else {
                if (is_array($value)) {
                    $yaml .= $prefix.$key.":\n".$this->arrayToYaml($value, $indent + 1);
                } else {
                    $yaml .= $prefix.$key.': '.$this->yamlValue($value)."\n";
                }
            }
        }

        return $yaml;
    }

    private function yamlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
